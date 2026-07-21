<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Family extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['church_id', 'campus_id', 'name', 'primary_contact_id', 'address'];

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
