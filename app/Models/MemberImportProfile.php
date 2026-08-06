<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MemberImportProfile extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = ['church_id', 'created_by', 'name', 'source_type', 'mapping', 'transformations', 'options', 'is_shared'];

    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'transformations' => 'array',
            'options' => 'array',
            'is_shared' => 'boolean',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
