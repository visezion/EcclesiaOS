<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class Approval extends Model
{
    use UsesOpaqueRouteKeys;
    protected $fillable = ['church_id', 'workflow_id', 'approvable_type', 'approvable_id', 'action', 'requested_by', 'approved_by', 'status', 'notes', 'payload', 'submitted_at', 'approved_at', 'rejected_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
