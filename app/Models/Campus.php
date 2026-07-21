<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Campus extends Model
{
    use UsesOpaqueRouteKeys;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['church_id', 'name', 'slug', 'type', 'city', 'country', 'address', 'capacity', 'map_x', 'map_y', 'metadata', 'status'];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'map_x' => 'decimal:2',
            'map_y' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function careTasks(): HasMany
    {
        return $this->hasMany(CareTask::class);
    }
}
