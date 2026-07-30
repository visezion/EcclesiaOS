<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageThread;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class EnforceMessageRetention extends Command
{
    protected $signature = 'messages:enforce-retention {--dry-run}';

    protected $description = 'Remove message content that has reached its configured retention date';

    public function handle(): int
    {
        $query = MessageThread::withTrashed()->whereNotNull('retention_until')->where('retention_until', '<=', now());
        $count = (clone $query)->count();
        if ($this->option('dry-run')) {
            $this->info($count.' conversations are due for retention cleanup.');

            return self::SUCCESS;
        }

        $query->chunkById(100, function ($threads): void {
            foreach ($threads as $thread) {
                MessageAttachment::query()
                    ->whereIn('message_id', Message::withTrashed()->where('message_thread_id', $thread->id)->select('messages.id'))
                    ->get()
                    ->each(fn (MessageAttachment $attachment) => Storage::disk($attachment->disk)->delete($attachment->path));
                $thread->forceDelete();
            }
        });
        $this->info($count.' conversations were removed by retention policy.');

        return self::SUCCESS;
    }
}
