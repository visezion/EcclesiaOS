<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Volunteer extends Model
{
    protected $fillable = ['church_id', 'campus_id', 'member_id', 'ministry_id', 'role', 'status', 'availability'];

    protected function casts(): array
    {
        return ['availability' => 'array'];
    }
}
