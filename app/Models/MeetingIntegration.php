<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MeetingIntegration extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = ['church_id', 'provider', 'enabled', 'settings', 'last_tested_at'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'settings' => 'array', 'last_tested_at' => 'datetime'];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
