<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;

use Illuminate\Database\Eloquent\Model;

final class Approval extends Model
{
    use UsesOpaqueRouteKeys;
    protected $fillable = ['church_id', 'workflow_id', 'approvable_type', 'approvable_id', 'requested_by', 'approved_by', 'status', 'notes', 'approved_at'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }
}
