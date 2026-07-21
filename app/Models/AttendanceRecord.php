<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class AttendanceRecord extends Model
{
    protected $fillable = ['church_id', 'campus_id', 'event_id', 'member_id', 'service_date', 'status', 'checked_in_at', 'metadata'];

    protected function casts(): array
    {
        return ['service_date' => 'date', 'checked_in_at' => 'datetime', 'metadata' => 'array'];
    }
}
