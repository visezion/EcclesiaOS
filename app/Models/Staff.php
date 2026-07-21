<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Staff extends Model
{
    use SoftDeletes;

    protected $fillable = ['church_id', 'campus_id', 'user_id', 'department', 'job_title', 'employment_status', 'started_at'];

    protected function casts(): array
    {
        return ['started_at' => 'date'];
    }
}
