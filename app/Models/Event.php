<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Event extends Model
{
    use UsesOpaqueRouteKeys;
    use SoftDeletes;

    protected $fillable = ['church_id', 'campus_id', 'title', 'starts_at', 'ends_at', 'venue', 'category', 'status'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
