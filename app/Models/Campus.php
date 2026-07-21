<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Campus extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['church_id', 'name', 'slug', 'city', 'country', 'address', 'status'];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
