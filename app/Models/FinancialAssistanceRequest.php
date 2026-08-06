<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class FinancialAssistanceRequest extends Model
{
    use SoftDeletes;
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'reference', 'church_id', 'requester_id', 'source_campus_id', 'target_campus_id',
        'category', 'beneficiary_type', 'beneficiary_name', 'title', 'purpose', 'justification',
        'amount', 'approved_amount', 'currency', 'needed_by', 'urgency',
        'preferred_payment_method', 'payee_name', 'status', 'current_stage',
        'decision_notes', 'disbursement_notes', 'disbursement_reference',
        'approved_by', 'disbursed_by', 'submitted_at', 'approved_at', 'rejected_at', 'disbursed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'needed_by' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'disbursed_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function sourceCampus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'source_campus_id');
    }

    public function targetCampus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'target_campus_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function disburser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function approval(): MorphOne
    {
        return $this->morphOne(Approval::class, 'approvable');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FinancialAssistanceAttachment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(FinancialAssistanceActivity::class);
    }
}
