<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Facility extends Model
{
    use SoftDeletes;

    protected $fillable = ['church_id', 'campus_id', 'name', 'type', 'capacity', 'status'];
}
