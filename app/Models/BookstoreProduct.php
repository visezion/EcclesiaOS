<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class BookstoreProduct extends Model
{
    use SoftDeletes;

    protected $fillable = ['church_id', 'campus_id', 'name', 'sku', 'category', 'price', 'stock_quantity', 'reorder_level', 'status'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }
}
