<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Ministry extends Model
{
    use UsesOpaqueRouteKeys;
    use SoftDeletes;

    protected $fillable = ['church_id', 'campus_id', 'name', 'leader_id', 'description', 'status'];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'leader_id');
    }

    public function volunteers(): HasMany
    {
        return $this->hasMany(Volunteer::class);
    }
}
