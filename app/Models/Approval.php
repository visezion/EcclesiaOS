<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Approval extends Model
{
    protected $fillable = ['church_id', 'workflow_id', 'approvable_type', 'approvable_id', 'requested_by', 'approved_by', 'status', 'notes', 'approved_at'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }
}
