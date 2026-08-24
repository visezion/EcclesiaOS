<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class YouTubeAppCredential extends Model
{
    protected $table = 'youtube_app_credentials';

    protected $fillable = ['church_id', 'client_id', 'client_secret', 'redirect_uri'];

    protected function casts(): array
    {
        return ['client_secret' => 'encrypted'];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
