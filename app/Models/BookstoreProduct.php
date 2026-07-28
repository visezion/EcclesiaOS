<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class BookstoreProduct extends Model
{
    use SoftDeletes;
    use UsesOpaqueRouteKeys;

    protected $fillable = [
        'church_id',
        'campus_id',
        'name',
        'sku',
        'category',
        'author',
        'isbn',
        'format',
        'publisher',
        'digital_url',
        'is_library_item',
        'borrowable',
        'rentable',
        'rental_price',
        'price',
        'stock_quantity',
        'reorder_level',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'rental_price' => 'decimal:2',
            'is_library_item' => 'boolean',
            'borrowable' => 'boolean',
            'rentable' => 'boolean',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(BookstoreOrderItem::class);
    }

    public function libraryLoans(): HasMany
    {
        return $this->hasMany(BookstoreLibraryLoan::class);
    }
}
