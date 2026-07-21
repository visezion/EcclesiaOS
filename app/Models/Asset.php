<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Asset extends Model
{
    use SoftDeletes;

    protected $fillable = ['church_id', 'campus_id', 'asset_category_id', 'name', 'serial_number', 'status', 'condition', 'purchased_at', 'purchase_amount'];

    protected function casts(): array
    {
        return ['purchased_at' => 'date', 'purchase_amount' => 'decimal:2'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }
}
