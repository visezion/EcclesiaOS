<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LeadershipReportTemplate extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'church_id',
        'user_id',
        'campus_id',
        'ministry_id',
        'assigned_to',
        'name',
        'description',
        'report_type',
        'priority',
        'summary',
        'metrics',
        'action_items',
    ];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'action_items' => 'array',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function ministry(): BelongsTo
    {
        return $this->belongsTo(Ministry::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
