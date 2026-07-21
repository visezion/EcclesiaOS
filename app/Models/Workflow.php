<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Workflow extends Model
{
    use UsesOpaqueRouteKeys;
    use SoftDeletes;

    protected $fillable = ['church_id', 'name', 'module', 'status', 'steps'];

    protected function casts(): array
    {
        return ['steps' => 'array'];
    }
}
