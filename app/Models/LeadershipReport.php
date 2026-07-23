<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class LeadershipReport extends Model
{
    use SoftDeletes;
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'church_id',
        'campus_id',
        'ministry_id',
        'submitted_by',
        'assigned_to',
        'reviewed_by',
        'title',
        'report_type',
        'period_start',
        'period_end',
        'status',
        'priority',
        'summary',
        'metrics',
        'action_items',
        'review_notes',
        'submitted_at',
        'reviewed_at',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'metrics' => 'array',
            'action_items' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function ministry(): BelongsTo
    {
        return $this->belongsTo(Ministry::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
