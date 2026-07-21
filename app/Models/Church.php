<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Church extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'timezone', 'currency', 'email', 'phone', 'address', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function campuses(): HasMany
    {
        return $this->hasMany(Campus::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
