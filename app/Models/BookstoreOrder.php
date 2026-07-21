<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class BookstoreOrder extends Model
{
    use UsesOpaqueRouteKeys;
    use SoftDeletes;

    protected $fillable = ['church_id', 'campus_id', 'member_id', 'order_number', 'total_amount', 'currency', 'status', 'ordered_at'];

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2', 'ordered_at' => 'datetime'];
    }
}
