<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Workflow extends Model
{
    use SoftDeletes;
    use UsesOpaqueRouteKeys;

    protected $fillable = ['church_id', 'name', 'module', 'status', 'steps'];

    protected function casts(): array
    {
        return ['steps' => 'array'];
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }
}
