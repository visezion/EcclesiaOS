<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Church;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

final class WebsiteDesignTransfer
{
    private const PAGE_FIELDS = ['title', 'slug', 'status', 'body', 'sections', 'design', 'seo_title', 'seo_description'];

    private const MAX_BYTES = 209715200;

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['design_package' => $message]);
    }

    private function walk(mixed $value, callable $transform): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $value[$key] = $this->walk($child, $transform);
            }

            return $value;
        }

        return is_string($value) ? $transform($value) : $value;
    }

    public function export(Church $church, array $settings): string
    {
        if (! class_exists(ZipArchive::class)) {
            $this->fail('Enable the PHP ZIP extension to transfer website designs.');
        }
        $zip = new ZipArchive;
        $file = tempnam(sys_get_temp_dir(), 'website-design-');
        if ($file === false || $zip->open($file, ZipArchive::OVERWRITE) !== true) {
            $this->fail('Unable to create the design package.');
        }
        try {
            $media = [];
            $bytes = 0;
            $disk = Storage::disk('public');
            $siteUrl = route('website.public', ['church' => $church->slug]);
            $sitePath = parse_url($siteUrl, PHP_URL_PATH);
            $data = ['settings' => $settings, 'pages' => $church->websitePages()->get()->map->only(self::PAGE_FIELDS)->all()];
            $data = $this->walk($data, function (string $value) use ($church, $disk, $zip, &$media, &$bytes, $siteUrl, $sitePath): string {
                $host = parse_url($value, PHP_URL_HOST);
                if ($host && ! in_array(strtolower($host), array_filter([strtolower((string) parse_url($siteUrl, PHP_URL_HOST)), strtolower((string) parse_url(config('app.url'), PHP_URL_HOST))]), true)) {
                    return $value;
                }
                $path = ltrim(rawurldecode((string) (parse_url($value, PHP_URL_PATH) ?? $value)), '/');
                if (($offset = strpos($path, 'storage/')) !== false) {
                    $path = substr($path, $offset + 8);
                }
                if (str_starts_with($path, 'website/') || str_starts_with($path, 'branding/')) {
                    // Only this church's uploads or its explicitly configured branding may be read.
                    $branding = array_filter([data_get($church->settings, 'logo'), data_get($church->settings, 'favicon')]);
                    if (str_contains($path, '..') || str_contains($path, '\\') || (! str_starts_with($path, 'website/'.$church->id.'/') && ! in_array($value, $branding, true))) {
                        $this->fail('A media reference is outside this church’s website library.');
                    }
                    if (! $disk->exists($path)) {
                        $this->fail('An uploaded media file is missing: '.$path);
                    }
                    if (! isset($media[$path])) {
                        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico', 'mp4', 'webm', 'ogg'], true)) {
                            $this->fail('Unsupported media format: '.$extension);
                        }
                        $bytes += $disk->size($path);
                        if ($bytes > self::MAX_BYTES) {
                            $this->fail('The design media exceeds the 200 MB package limit.');
                        }
                        if (count($media) >= 2000) {
                            $this->fail('A design package can contain up to 2,000 media files.');
                        }
                        $entry = 'media/'.count($media).'.'.$extension;
                        if (! $zip->addFile($disk->path($path), $entry)) {
                            $this->fail('Unable to add media to the package.');
                        }
                        $media[$path] = $entry;
                    }

                    return '@media/'.$media[$path];
                }
                foreach ([$siteUrl, $sitePath] as $prefix) {
                    if ($value === $prefix || str_starts_with($value, $prefix.'/') || str_starts_with($value, $prefix.'#') || str_starts_with($value, $prefix.'?')) {
                        return '@site'.substr($value, strlen($prefix));
                    }
                }

                return $value;
            });
            $manifest = json_encode(['format' => 'ecclesiaos-website-design', 'version' => 1, 'data' => $data], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            if (strlen($manifest) > 5242880 || count($data['pages']) > 100) {
                $this->fail('The design exceeds the limit of 100 pages or 5 MB of design settings.');
            }
            $zip->addFromString('design.json', $manifest);
            if (! $zip->close()) {
                $this->fail('Unable to finish the design package.');
            }

            return $file;
        } catch (\Throwable $error) {
            @$zip->close();
            @unlink($file);
            throw $error;
        }
    }

    public function import(Church $church, string $file): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->fail('Enable the PHP ZIP extension to transfer website designs.');
        }
        $zip = new ZipArchive;
        if ($zip->open($file) !== true) {
            $this->fail('Upload a valid Website Studio ZIP package.');
        }
        $directory = 'website/'.$church->id.'/imports/'.Str::uuid();
        $disk = Storage::disk('public');
        try {
            $total = 0;
            $entries = [];
            if ($zip->numFiles > 2001) {
                $this->fail('The package contains too many files.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                $total += $stat['size'];
                if (isset($entries[$name]) || ($name !== 'design.json' && ! preg_match('~^media/[0-9]+\.(jpg|jpeg|png|gif|webp|ico|mp4|webm|ogg)$~D', $name)) || $total > self::MAX_BYTES + 5242880 || ($name === 'design.json' && $stat['size'] > 5242880)) {
                    $this->fail('The package contains invalid files or exceeds the size limit.');
                }
                $entries[$name] = true;
            }
            try {
                $manifest = json_decode($zip->getFromName('design.json') ?: '', true, 64, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $this->fail('The design manifest is missing or invalid.');
            }
            if (! is_array($manifest) || ($manifest['format'] ?? null) !== 'ecclesiaos-website-design' || ($manifest['version'] ?? null) !== 1) {
                $this->fail('This design package format is not supported.');
            }
            $data = $manifest['data'] ?? [];
            if (! is_array($data)) {
                $this->fail('The design data must be an object.');
            }
            Validator::make($data, [
                'settings' => ['required', 'array'],
                'settings.template' => ['required', 'in:main'],
                'settings.custom_sections' => ['sometimes', 'array'],
                'settings.media_library' => ['sometimes', 'array'],
                'settings.media_library.*' => ['array'],
                'settings.media_library.*.path' => ['required', 'string'],
                'settings.custom_sections.*' => ['array'],
                'settings.custom_sections.*.id' => ['required', 'string', 'max:80', 'distinct'],
                'settings.custom_sections.*.title' => ['required', 'string', 'max:180'],
                'settings.custom_sections.*.page_slugs' => ['sometimes', 'array'],
                'settings.custom_sections.*.components' => ['sometimes', 'array'],
                'settings.navigation' => ['sometimes', 'array'],
                'settings.navigation.*' => ['array'],
                'settings.navigation.*.label' => ['required', 'string'],
                'settings.navigation.*.url' => ['required', 'string'],
                'settings.hero_slides' => ['sometimes', 'array'],
                'settings.hero_slides.*' => ['array'],
                'pages' => ['required', 'array', 'min:1', 'max:100'],
                'pages.*' => ['array'],
                'pages.*.title' => ['required', 'string', 'max:150'],
                'pages.*.slug' => ['required', 'alpha_dash', 'max:100', 'distinct'],
                'pages.*.status' => ['required', 'in:draft,published'],
                'pages.*.body' => ['nullable', 'string', 'max:30000'],
                'pages.*.sections' => ['nullable', 'array'],
                'pages.*.design' => ['nullable', 'array'],
                'pages.*.seo_title' => ['nullable', 'string', 'max:180'],
                'pages.*.seo_description' => ['nullable', 'string', 'max:180'],
            ])->validate();
            if (! in_array('home', array_column($data['pages'], 'slug'), true)) {
                $this->fail('The package must include a homepage.');
            }
            $media = [];
            $data = $this->walk($data, function (string $value) use ($zip, $directory, &$media): string {
                if (preg_match('/^\s*(javascript|vbscript|data):/i', $value)) {
                    $this->fail('The package contains an unsafe link.');
                }
                $localPath = ltrim((string) parse_url($value, PHP_URL_PATH), '/');
                if (! parse_url($value, PHP_URL_HOST) && preg_match('~^(?:storage/)?(?:website|branding)/~', $localPath)) {
                    $this->fail('The package contains media that was not bundled by Website Studio.');
                }
                if (str_starts_with($value, '@media/')) {
                    $entry = substr($value, 7);
                    if (! preg_match('~^media/[0-9]+\.(jpg|jpeg|png|gif|webp|ico|mp4|webm|ogg)$~D', $entry) || $zip->locateName($entry) === false) {
                        $this->fail('A referenced media file is missing or invalid.');
                    }
                    $media[$entry] = $directory.'/'.basename($entry);

                    return $media[$entry];
                }

                return $value;
            });
            $siteUrl = route('website.public', ['church' => $church->slug]);
            $data = $this->walk($data, fn (string $value): string => preg_match('~^@site(?:[/#?]|$)~', $value) ? $siteUrl.substr($value, 5) : $value);
            foreach ($media as $entry => $path) {
                $stream = $zip->getStream($entry);
                if ($stream === false) {
                    $this->fail('A media file could not be read.');
                }
                try {
                    if (! $disk->put($path, $stream)) {
                        $this->fail('A media file could not be saved.');
                    }
                } finally {
                    fclose($stream);
                }
                $mime = $disk->mimeType($path);
                if (! in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon', 'video/mp4', 'video/webm', 'video/ogg', 'audio/ogg', 'application/ogg'], true)) {
                    $this->fail('A packaged media file has an unsupported content type.');
                }
            }
            DB::transaction(function () use ($church, $data): void {
                $current = Church::query()->lockForUpdate()->findOrFail($church->id);
                $current->forceFill(['settings' => array_merge($current->settings ?? [], ['website' => $data['settings']])])->save();
                $current->websitePages()->whereNotIn('slug', array_column($data['pages'], 'slug'))->delete();
                foreach ($data['pages'] as $page) {
                    $record = $current->websitePages()->withTrashed()->firstOrNew(['slug' => $page['slug']]);
                    $record->fill(array_intersect_key($page, array_flip(self::PAGE_FIELDS)));
                    $record->deleted_at = null;
                    $record->published_at = $page['status'] === 'published' ? now() : null;
                    $record->save();
                }
            });
        } catch (\Throwable $error) {
            $disk->deleteDirectory($directory);
            throw $error;
        } finally {
            $zip->close();
        }
    }
}
