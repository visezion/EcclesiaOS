<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Family extends Model
{
    use UsesOpaqueRouteKeys;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['church_id', 'campus_id', 'name', 'primary_contact_id', 'address'];

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function primaryContact(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'primary_contact_id');
    }
}
