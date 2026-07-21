<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Donation extends Model
{
    use UsesOpaqueRouteKeys;
    use SoftDeletes;

    protected $fillable = ['church_id', 'campus_id', 'member_id', 'fund_id', 'amount', 'currency', 'method', 'received_at', 'reference'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'received_at' => 'datetime'];
    }
}
