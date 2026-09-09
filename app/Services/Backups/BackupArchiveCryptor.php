<?php

declare(strict_types=1);

namespace App\Services\Backups;

use RuntimeException;

final class BackupArchiveCryptor
{
    private const MAGIC = "ECCBACKUP1\n";

    private const CHUNK_SIZE = 1048576;

    public function encrypt(string $source, string $destination): void
    {
        $input = fopen($source, 'rb');
        $output = fopen($destination, 'wb');
        if ($input === false || $output === false) {
            throw new RuntimeException('The backup encryption streams could not be opened.');
        }

        $salt = random_bytes(32);
        $iv = random_bytes(16);
        [$encryptionKey, $authenticationKey] = $this->keys($salt);
        $prefix = self::MAGIC.$salt.$iv;
        fwrite($output, $prefix);
        $hmac = hash_init('sha256', HASH_HMAC, $authenticationKey);
        hash_update($hmac, $prefix);
        $counter = $iv;

        try {
            while (! feof($input)) {
                $plain = fread($input, self::CHUNK_SIZE);
                if ($plain === false) {
                    throw new RuntimeException('The backup archive could not be read during encryption.');
                }
                if ($plain === '') {
                    continue;
                }

                $cipher = openssl_encrypt($plain, 'aes-256-ctr', $encryptionKey, OPENSSL_RAW_DATA, $counter);
                if ($cipher === false) {
                    throw new RuntimeException('OpenSSL could not encrypt the backup archive.');
                }
                fwrite($output, $cipher);
                hash_update($hmac, $cipher);
                $counter = $this->incrementCounter($counter, (int) ceil(strlen($plain) / 16));
            }

            fwrite($output, hash_final($hmac, true));
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    public function decrypt(string $source, string $destination): void
    {
        $size = filesize($source);
        $headerSize = strlen(self::MAGIC) + 48;
        if ($size === false || $size < $headerSize + 32) {
            throw new RuntimeException('The backup package is incomplete.');
        }

        $input = fopen($source, 'rb');
        if ($input === false) {
            throw new RuntimeException('The backup package could not be opened.');
        }
        $prefix = fread($input, $headerSize);
        if ($prefix === false || ! str_starts_with($prefix, self::MAGIC)) {
            fclose($input);
            throw new RuntimeException('The file is not an EcclesiaOS encrypted backup package.');
        }

        $salt = substr($prefix, strlen(self::MAGIC), 32);
        $iv = substr($prefix, strlen(self::MAGIC) + 32, 16);
        [$encryptionKey, $authenticationKey] = $this->keys($salt);
        $cipherSize = $size - $headerSize - 32;
        $hmac = hash_init('sha256', HASH_HMAC, $authenticationKey);
        hash_update($hmac, $prefix);
        $remaining = $cipherSize;
        while ($remaining > 0) {
            $cipher = fread($input, min(self::CHUNK_SIZE, $remaining));
            if ($cipher === false || $cipher === '') {
                fclose($input);
                throw new RuntimeException('The backup package ended unexpectedly.');
            }
            hash_update($hmac, $cipher);
            $remaining -= strlen($cipher);
        }
        $storedTag = fread($input, 32);
        $expectedTag = hash_final($hmac, true);
        if ($storedTag === false || ! hash_equals($expectedTag, $storedTag)) {
            fclose($input);
            throw new RuntimeException('Backup authentication failed. The package is corrupted or uses a different encryption key.');
        }

        rewind($input);
        fseek($input, $headerSize);
        $output = fopen($destination, 'wb');
        if ($output === false) {
            fclose($input);
            throw new RuntimeException('The decrypted backup destination could not be opened.');
        }
        $counter = $iv;
        $remaining = $cipherSize;

        try {
            while ($remaining > 0) {
                $cipher = fread($input, min(self::CHUNK_SIZE, $remaining));
                if ($cipher === false || $cipher === '') {
                    throw new RuntimeException('The backup package ended unexpectedly during decryption.');
                }
                $plain = openssl_decrypt($cipher, 'aes-256-ctr', $encryptionKey, OPENSSL_RAW_DATA, $counter);
                if ($plain === false) {
                    throw new RuntimeException('OpenSSL could not decrypt the backup package.');
                }
                fwrite($output, $plain);
                $counter = $this->incrementCounter($counter, (int) ceil(strlen($cipher) / 16));
                $remaining -= strlen($cipher);
            }
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    public function isEncryptedPackage(string $path): bool
    {
        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            return false;
        }
        $magic = fread($stream, strlen(self::MAGIC));
        fclose($stream);

        return $magic === self::MAGIC;
    }

    /** @return array{0: string, 1: string} */
    private function keys(string $salt): array
    {
        $configured = (string) config('backups.encryption_key');
        if (str_starts_with($configured, 'base64:')) {
            $configured = base64_decode(substr($configured, 7), true) ?: '';
        }
        if ($configured === '') {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY or APP_KEY must be configured before backups can be encrypted.');
        }

        return [
            hash_hkdf('sha256', $configured, 32, 'ecclesiaos-backup-encryption', $salt),
            hash_hkdf('sha256', $configured, 32, 'ecclesiaos-backup-authentication', $salt),
        ];
    }

    private function incrementCounter(string $counter, int $blocks): string
    {
        $bytes = array_values(unpack('C*', $counter));
        for ($index = 15; $index >= 0 && $blocks > 0; $index--) {
            $sum = $bytes[$index] + ($blocks & 0xFF);
            $bytes[$index] = $sum & 0xFF;
            $blocks = ($blocks >> 8) + ($sum >> 8);
        }

        return pack('C*', ...$bytes);
    }
}
