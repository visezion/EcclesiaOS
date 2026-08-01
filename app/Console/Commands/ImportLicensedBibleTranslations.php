<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BibleTranslation;
use App\Models\Church;
use App\Models\User;
use App\Support\BibleTranslationImporter;
use Illuminate\Console\Command;

final class ImportLicensedBibleTranslations extends Command
{
    protected $signature = 'bible:import-licensed
        {directory : Directory containing tbl_ABBREVIATION.json files}
        {--church= : Church ID that is authorized to use the supplied files}
        {--user= : Optional importing user ID}';

    protected $description = 'Import church-supplied licensed Bible translation JSON files without publishing them';

    private const DEFINITIONS = [
        'AMP' => ['name' => 'Amplified Bible', 'source_url' => 'https://www.lockman.org/amplified-bible-amp/'],
        'KJV' => ['name' => 'King James Version', 'source_url' => 'https://ebible.org/eng-kjv/'],
        'MSG' => ['name' => 'The Message', 'source_url' => 'https://messagebible.com/'],
        'NIV' => ['name' => 'New International Version', 'source_url' => 'https://www.biblica.com/versions/niv-bible/'],
        'NKJV' => ['name' => 'New King James Version', 'source_url' => 'https://www.thomasnelsonbibles.com/nkjv/'],
        'NLT' => ['name' => 'New Living Translation', 'source_url' => 'https://www.tyndale.com/nlt/'],
        'NRSV' => ['name' => 'New Revised Standard Version', 'source_url' => 'https://www.nrsvbibles.org/'],
    ];

    public function handle(BibleTranslationImporter $importer): int
    {
        $directory = rtrim((string) realpath((string) $this->argument('directory')), DIRECTORY_SEPARATOR);
        if ($directory === '' || ! is_dir($directory)) {
            $this->error('The translation directory does not exist.');

            return self::FAILURE;
        }

        $churchId = (int) $this->option('church');
        if ($churchId < 1 || ! Church::query()->whereKey($churchId)->exists()) {
            $this->error('Provide a valid --church ID that is licensed to use these translations.');

            return self::FAILURE;
        }

        $userId = $this->option('user') ? (int) $this->option('user') : null;
        if ($userId && ! User::query()->whereKey($userId)->where('church_id', $churchId)->exists()) {
            $this->error('The importing user does not belong to the selected church.');

            return self::FAILURE;
        }

        $imported = 0;
        foreach (self::DEFINITIONS as $abbreviation => $definition) {
            $path = $directory.DIRECTORY_SEPARATOR.'tbl_'.$abbreviation.'.json';
            if (! is_file($path)) {
                continue;
            }

            $translation = BibleTranslation::query()->firstOrNew([
                'church_id' => $churchId,
                'abbreviation' => $abbreviation,
            ]);
            $translation->fill([
                'created_by' => $translation->created_by ?: $userId,
                'name' => $definition['name'],
                'language' => 'English',
                'description' => 'Church-provided translation imported from an authorized local source.',
                'copyright' => $abbreviation === 'KJV' ? 'Public domain' : 'Copyrighted; church-supplied licensed text. Redistribution prohibited.',
                'source_url' => $definition['source_url'],
                'status' => 'active',
                'is_default' => $translation->exists ? $translation->is_default : false,
            ]);
            $translation->save();

            $count = $importer->import($translation, $path);
            $this->info($abbreviation.': '.number_format($count).' verses imported.');
            $imported++;
        }

        if ($imported === 0) {
            $this->error('No supported tbl_ABBREVIATION.json files were found.');

            return self::FAILURE;
        }

        $this->info($imported.' translation files imported for church '.$churchId.'.');

        return self::SUCCESS;
    }
}
