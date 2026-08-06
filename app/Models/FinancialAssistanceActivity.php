<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FinancialAssistanceActivity extends Model
{
    protected $fillable = ['financial_assistance_request_id', 'user_id', 'type', 'description', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(FinancialAssistanceRequest::class, 'financial_assistance_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
