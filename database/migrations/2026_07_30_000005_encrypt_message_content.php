<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('messages')->orderBy('id')->chunkById(100, function ($messages): void {
            foreach ($messages as $message) {
                DB::table('messages')->where('id', $message->id)->update([
                    'body' => Crypt::encryptString((string) $message->body),
                    'body_html' => $message->body_html === null ? null : Crypt::encryptString((string) $message->body_html),
                ]);
            }
        });

        DB::table('message_drafts')->orderBy('id')->chunkById(100, function ($drafts): void {
            foreach ($drafts as $draft) {
                DB::table('message_drafts')->where('id', $draft->id)->update([
                    'body' => $draft->body === null ? null : Crypt::encryptString((string) $draft->body),
                    'body_html' => $draft->body_html === null ? null : Crypt::encryptString((string) $draft->body_html),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('messages')->orderBy('id')->chunkById(100, function ($messages): void {
            foreach ($messages as $message) {
                DB::table('messages')->where('id', $message->id)->update([
                    'body' => Crypt::decryptString((string) $message->body),
                    'body_html' => $message->body_html === null ? null : Crypt::decryptString((string) $message->body_html),
                ]);
            }
        });

        DB::table('message_drafts')->orderBy('id')->chunkById(100, function ($drafts): void {
            foreach ($drafts as $draft) {
                DB::table('message_drafts')->where('id', $draft->id)->update([
                    'body' => $draft->body === null ? null : Crypt::decryptString((string) $draft->body),
                    'body_html' => $draft->body_html === null ? null : Crypt::decryptString((string) $draft->body_html),
                ]);
            }
        });
    }
};
