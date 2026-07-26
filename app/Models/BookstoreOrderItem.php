<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BookstoreOrderItem extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'bookstore_order_id',
        'bookstore_product_id',
        'product_name',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(BookstoreOrder::class, 'bookstore_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(BookstoreProduct::class, 'bookstore_product_id');
    }
}
