<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;

use Illuminate\Database\Eloquent\Model;

final class Setting extends Model
{
    use UsesOpaqueRouteKeys;
    protected $fillable = ['church_id', 'key', 'value', 'type'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
