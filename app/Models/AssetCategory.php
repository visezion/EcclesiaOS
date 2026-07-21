<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AssetCategory extends Model
{
    protected $fillable = ['church_id', 'name', 'description'];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
