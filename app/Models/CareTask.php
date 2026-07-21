<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CareTask extends Model
{
    use UsesOpaqueRouteKeys;
    use SoftDeletes;

    protected $fillable = [
        'church_id',
        'campus_id',
        'member_id',
        'assigned_user_id',
        'type',
        'priority',
        'status',
        'next_action',
        'notes',
        'due_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
