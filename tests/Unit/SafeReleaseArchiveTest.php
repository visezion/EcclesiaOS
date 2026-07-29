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
    private string $testPath;

    protected function setUp(): void
    {
        parent::setUp();

        config(['updater.max_expanded_bytes' => 1024 * 1024]);
        $this->testPath = storage_path('framework/testing/updater-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->testPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testPath);

        parent::tearDown();
    }

    public function test_standard_dot_prefixed_release_paths_are_normalized(): void
    {
        $archive = $this->archive([
            './' => null,
            './artisan' => 'application',
            './public/index.php' => '<?php',
        ]);
        $destination = $this->testPath.DIRECTORY_SEPARATOR.'release';

        app(SafeReleaseArchive::class)->extract($archive, $destination);

        $this->assertFileExists($destination.DIRECTORY_SEPARATOR.'artisan');
        $this->assertFileExists($destination.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'index.php');
    }

    public function test_dot_prefixed_protected_data_paths_are_rejected_before_extraction(): void
    {
        $archive = $this->archive([
            './artisan' => 'application',
            './storage/app/church-data.txt' => 'must not be overwritten',
        ]);
        $destination = $this->testPath.DIRECTORY_SEPARATOR.'release';

        try {
            app(SafeReleaseArchive::class)->extract($archive, $destination);
            $this->fail('The protected path should have been rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('protected church data paths', $exception->getMessage());
        }

        $this->assertDirectoryDoesNotExist($destination);
    }

    public function test_parent_directory_traversal_is_rejected(): void
    {
        $archive = $this->archive(['../outside.txt' => 'unsafe']);
        $destination = $this->testPath.DIRECTORY_SEPARATOR.'release';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsafe path');

        app(SafeReleaseArchive::class)->extract($archive, $destination);
    }

    /**
     * @param  array<string, string|null>  $entries
     */
    private function archive(array $entries): string
    {
        $path = $this->testPath.DIRECTORY_SEPARATOR.'release-'.bin2hex(random_bytes(4)).'.zip';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));

        foreach ($entries as $name => $contents) {
            if ($contents === null) {
                $zip->addEmptyDir($name);
            } else {
                $zip->addFromString($name, $contents);
            }
        }

        $this->assertTrue($zip->close());

        return $path;
    }
}
