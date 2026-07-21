<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Role extends Model
{
    use UsesOpaqueRouteKeys;
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
