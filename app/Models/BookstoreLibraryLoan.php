<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class BookstoreLibraryLoan extends Model
{
    use UsesOpaqueRouteKeys;
    use SoftDeletes;

    protected $fillable = [
        'church_id',
        'campus_id',
        'bookstore_product_id',
        'member_id',
        'handled_by_user_id',
        'loan_number',
        'loan_type',
        'status',
        'checked_out_at',
        'due_at',
        'returned_at',
        'rental_amount',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'checked_out_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
            'rental_amount' => 'decimal:2',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(BookstoreProduct::class, 'bookstore_product_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }
}
