<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Ministry extends Model
{
    use SoftDeletes;

    protected $fillable = ['church_id', 'campus_id', 'name', 'leader_id', 'description', 'status'];

    public function volunteers(): HasMany
    {
        return $this->hasMany(Volunteer::class);
    }
}
