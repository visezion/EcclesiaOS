<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FinancialAssistanceAttachment extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'financial_assistance_request_id', 'uploaded_by', 'kind', 'disk', 'path',
        'original_name', 'mime_type', 'size', 'sha256',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(FinancialAssistanceRequest::class, 'financial_assistance_request_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
