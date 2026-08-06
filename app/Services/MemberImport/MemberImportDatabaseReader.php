<?php

declare(strict_types=1);

namespace App\Services\MemberImport;

use App\Models\MemberImportConnection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

final class MemberImportDatabaseReader
{
    public const DRIVERS = [
        'mysql' => ['label' => 'MySQL', 'default_port' => 3306],
        'pgsql' => ['label' => 'PostgreSQL', 'default_port' => 5432],
        'sqlsrv' => ['label' => 'SQL Server', 'default_port' => 1433],
        'sqlite' => ['label' => 'SQLite', 'default_port' => null],
    ];

    /**
     * @return array<string, array{label: string, default_port: ?int, available: bool}>
     */
    public function capabilities(): array
    {
        $available = PDO::getAvailableDrivers();

        return collect(self::DRIVERS)->map(fn (array $driver, string $key): array => [
            ...$driver,
            'available' => in_array($key, $available, true),
        ])->all();
    }

    public function test(MemberImportConnection $connection): array
    {
        $started = microtime(true);
        $pdo = $this->connect($connection);
        $pdo->query($connection->driver === 'sqlsrv' ? 'SELECT 1 AS connection_test' : 'SELECT 1 AS connection_test')->fetch();

        return [
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'tables' => count($this->tables($connection, $pdo)),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tables(MemberImportConnection $connection, ?PDO $pdo = null): array
    {
        $pdo ??= $this->connect($connection);
        $schema = $this->schema($connection);
        $tables = match ($connection->driver) {
            'mysql' => $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = :database AND table_type IN ('BASE TABLE', 'VIEW') ORDER BY table_name"),
            'pgsql' => $pdo->prepare("SELECT table_schema || '.' || table_name FROM information_schema.tables WHERE table_schema = :schema AND table_type IN ('BASE TABLE', 'VIEW') ORDER BY table_name"),
            'sqlsrv' => $pdo->prepare("SELECT TABLE_SCHEMA + '.' + TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE IN ('BASE TABLE', 'VIEW') ORDER BY TABLE_SCHEMA, TABLE_NAME"),
            'sqlite' => $pdo->prepare("SELECT name FROM sqlite_master WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%' ORDER BY name"),
            default => throw new RuntimeException('Unsupported database driver.'),
        };
        $parameters = match ($connection->driver) {
            'mysql' => ['database' => $connection->database_name],
            'pgsql' => ['schema' => $schema],
            default => [],
        };
        $tables->execute($parameters);

        return array_values(array_filter(array_map('strval', $tables->fetchAll(PDO::FETCH_COLUMN))));
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    public function read(MemberImportConnection $connection, string $table, int $limit = 25000): array
    {
        $pdo = $this->connect($connection);
        if (! in_array($table, $this->tables($connection, $pdo), true)) {
            throw new RuntimeException('The selected table is not available through this read-only connection.');
        }
        $limit = min(25000, max(1, $limit));
        $quoted = $this->quoteIdentifier($connection->driver, $table);
        $sql = $connection->driver === 'sqlsrv'
            ? "SELECT TOP {$limit} * FROM {$quoted}"
            : "SELECT * FROM {$quoted} LIMIT {$limit}";
        $statement = $pdo->query($sql);
        $rawRows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rawRows === []) {
            throw new RuntimeException('The selected table contains no rows.');
        }
        $headerMap = collect(array_keys($rawRows[0]))->mapWithKeys(fn (string $header): array => [$header => $this->header($header)])->all();
        $headers = array_values($headerMap);
        $rows = collect($rawRows)->map(function (array $row) use ($headerMap): array {
            return collect($row)->mapWithKeys(function ($value, string $key) use ($headerMap): array {
                if (is_resource($value)) {
                    $value = stream_get_contents($value);
                }

                return [$headerMap[$key] => $value];
            })->all();
        })->all();

        return compact('headers', 'rows');
    }

    public function connect(MemberImportConnection $connection): PDO
    {
        if (! in_array($connection->driver, PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException(self::DRIVERS[$connection->driver]['label'].' requires the pdo_'.$connection->driver.' PHP extension on this server.');
        }
        $password = $connection->password_encrypted ? Crypt::decryptString($connection->password_encrypted) : null;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if ($connection->driver === 'sqlite') {
            $path = Storage::disk('local')->path($connection->database_name);
            if (! is_file($path)) {
                throw new RuntimeException('The uploaded SQLite database is unavailable.');
            }
            $options[PDO::SQLITE_ATTR_OPEN_FLAGS] = PDO::SQLITE_OPEN_READONLY;
            $pdo = new PDO('sqlite:'.$path, null, null, $options);
        } else {
            $pdo = new PDO($this->dsn($connection), $connection->username, $password, $options);
        }
        match ($connection->driver) {
            'mysql' => $pdo->exec('SET SESSION TRANSACTION READ ONLY'),
            'pgsql' => $pdo->exec('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY'),
            default => null,
        };

        return $pdo;
    }

    private function dsn(MemberImportConnection $connection): string
    {
        $host = trim((string) $connection->host);
        $port = (int) ($connection->port ?: self::DRIVERS[$connection->driver]['default_port']);

        return match ($connection->driver) {
            'mysql' => "mysql:host={$host};port={$port};dbname={$connection->database_name};charset=utf8mb4",
            'pgsql' => "pgsql:host={$host};port={$port};dbname={$connection->database_name};sslmode=".data_get($connection->options, 'sslmode', 'prefer'),
            'sqlsrv' => "sqlsrv:Server={$host},{$port};Database={$connection->database_name};Encrypt=".((bool) data_get($connection->options, 'encrypt', true) ? 'yes' : 'no').';TrustServerCertificate='.((bool) data_get($connection->options, 'trust_server_certificate', false) ? 'yes' : 'no'),
            default => throw new RuntimeException('Unsupported database driver.'),
        };
    }

    private function schema(MemberImportConnection $connection): string
    {
        $schema = (string) data_get($connection->options, 'schema', 'public');

        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $schema) ? $schema : 'public';
    }

    private function quoteIdentifier(string $driver, string $identifier): string
    {
        $parts = explode('.', $identifier);
        foreach ($parts as $part) {
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_$]*$/', $part)) {
                throw new RuntimeException('The selected table name is not safe to query.');
            }
        }

        return collect($parts)->map(fn (string $part): string => match ($driver) {
            'mysql' => '`'.$part.'`',
            'sqlsrv' => '['.$part.']',
            default => '"'.$part.'"',
        })->implode('.');
    }

    private function header(string $header): string
    {
        $header = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', trim($header));
        $header = trim((string) preg_replace('/[^a-z0-9]+/i', '_', Str::ascii((string) $header)), '_');

        return Str::lower($header);
    }
}
