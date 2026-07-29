<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Updates\SafeReleaseArchive;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

final class SafeReleaseArchiveTest extends TestCase
{
    private string $temporaryPath;

    protected function setUp(): void
    {
        parent::setUp();

        config(['updater.max_expanded_bytes' => 10_000_000]);
        $this->temporaryPath = storage_path('framework/testing/updater-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->temporaryPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryPath);

        parent::tearDown();
    }

    public function test_it_extracts_a_valid_release_archive(): void
    {
        $archive = $this->zip([
            'artisan' => '#!/usr/bin/env php',
            'public/index.php' => '<?php',
            'VERSION' => '2.1.0',
        ]);
        $destination = $this->temporaryPath.DIRECTORY_SEPARATOR.'release';

        app(SafeReleaseArchive::class)->extract($archive, $destination);

        $this->assertFileExists($destination.DIRECTORY_SEPARATOR.'artisan');
        $this->assertFileExists($destination.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php');
        $this->assertSame('2.1.0', File::get($destination.DIRECTORY_SEPARATOR.'VERSION'));
    }

    public function test_it_rejects_path_traversal(): void
    {
        $archive = $this->zip(['../outside.php' => '<?php']);
        $destination = $this->temporaryPath.DIRECTORY_SEPARATOR.'release';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsafe path');

        try {
            app(SafeReleaseArchive::class)->extract($archive, $destination);
        } finally {
            $this->assertFileDoesNotExist($this->temporaryPath.DIRECTORY_SEPARATOR.'outside.php');
        }
    }

    public function test_it_rejects_church_owned_data_paths(): void
    {
        $archive = $this->zip([
            'artisan' => '#!/usr/bin/env php',
            '.env' => 'APP_KEY=secret',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('protected church data paths');

        app(SafeReleaseArchive::class)->extract(
            $archive,
            $this->temporaryPath.DIRECTORY_SEPARATOR.'release',
        );
    }

    public function test_it_rejects_dot_prefixed_church_owned_data_paths_before_extraction(): void
    {
        $archive = $this->zip([
            './artisan' => '#!/usr/bin/env php',
            './storage/app/church-data.txt' => 'must not be overwritten',
        ]);
        $destination = $this->temporaryPath.DIRECTORY_SEPARATOR.'release';

        try {
            app(SafeReleaseArchive::class)->extract($archive, $destination);
            $this->fail('The protected path should have been rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('protected church data paths', $exception->getMessage());
        }

        $this->assertDirectoryDoesNotExist($destination);
    }

    /**
     * @param  array<string, string>  $files
     */
    private function zip(array $files): string
    {
        $path = $this->temporaryPath.DIRECTORY_SEPARATOR.bin2hex(random_bytes(4)).'.zip';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));

        foreach ($files as $name => $contents) {
            $this->assertTrue($zip->addFromString($name, $contents));
        }

        $this->assertTrue($zip->close());

        return $path;
    }
}
