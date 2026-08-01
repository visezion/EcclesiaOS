<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\BibleFreeTranslationInstaller;
use Illuminate\Console\Command;

final class DownloadFreeBibleTranslations extends Command
{
    protected $signature = 'bible:download-free {--force : Redownload existing local files}';

    protected $description = 'Download the free Bible translation bundle into local application storage';

    public function handle(): int
    {
        foreach (array_keys(BibleFreeTranslationInstaller::sources()) as $abbreviation) {
            $this->info('Downloading '.$abbreviation.'...');
            BibleFreeTranslationInstaller::download($abbreviation, (bool) $this->option('force'));
        }
        $this->info('Free Bible translation files are stored in storage/app/private/bible/free.');

        return self::SUCCESS;
    }
}
