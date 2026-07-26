<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AssetCategory extends Model
{
    use UsesOpaqueRouteKeys;
    protected $fillable = ['church_id', 'name', 'description'];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
