<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class LucideIconRegistrationTest extends TestCase
{
    public function test_every_static_lucide_icon_used_by_a_blade_view_is_registered(): void
    {
        $javascript = file_get_contents(resource_path('js/app.js'));
        $this->assertIsString($javascript);
        $this->assertSame(1, preg_match('/const icons = \{(?<icons>.*?)\};/s', $javascript, $iconBlock));
        preg_match_all('/^\s*(?<name>[A-Za-z][A-Za-z0-9]*)\s*,\s*$/m', $iconBlock['icons'], $registeredMatches);
        $registered = array_fill_keys($registeredMatches['name'], true);
        $missing = [];
        $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

        /** @var SplFileInfo $view */
        foreach ($views as $view) {
            if (! $view->isFile() || ! str_ends_with($view->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($view->getPathname());
            if (! is_string($contents)) {
                continue;
            }

            preg_match_all('/data-lucide\s*=\s*["\'](?<name>[^"\']+)["\']/', $contents, $usedMatches);
            foreach ($usedMatches['name'] as $icon) {
                if (str_contains($icon, '{{')) {
                    continue;
                }

                $export = Str::studly($icon);
                if (! isset($registered[$export])) {
                    $missing[$icon][] = Str::after($view->getPathname(), base_path().DIRECTORY_SEPARATOR);
                }
            }
        }

        ksort($missing);
        $this->assertSame([], $missing, 'Some static Lucide icons are used but not registered in resources/js/app.js.');
    }
}
