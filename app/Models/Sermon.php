<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Sermon extends Model
{
    use SoftDeletes;
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'church_id',
        'title',
        'slug',
        'speaker',
        'scripture',
        'summary',
        'preached_at',
        'video_url',
        'audio_url',
        'thumbnail_url',
        'status',
    ];

    protected function casts(): array
    {
        return ['preached_at' => 'date'];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
