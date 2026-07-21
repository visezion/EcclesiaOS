<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Feedback extends Model
{
    use SoftDeletes;

    protected $fillable = ['church_id', 'campus_id', 'member_id', 'type', 'subject', 'message', 'sentiment', 'status', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }
}
