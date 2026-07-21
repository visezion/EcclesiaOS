<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ActivityLog extends Model
{
    protected $fillable = ['church_id', 'campus_id', 'user_id', 'module', 'action', 'description', 'properties'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }
}
