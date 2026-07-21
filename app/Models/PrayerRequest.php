<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class PrayerRequest extends Model
{
    use SoftDeletes;

    protected $fillable = ['church_id', 'campus_id', 'member_id', 'title', 'request', 'status', 'is_confidential', 'followed_up_at'];

    protected function casts(): array
    {
        return ['is_confidential' => 'boolean', 'followed_up_at' => 'datetime'];
    }
}
