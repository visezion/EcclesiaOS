<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BibleVerse extends Model
{
    use HasFactory;

    protected $fillable = ['bible_translation_id', 'book', 'book_slug', 'testament', 'chapter', 'verse', 'text'];

    public function translation(): BelongsTo
    {
        return $this->belongsTo(BibleTranslation::class, 'bible_translation_id');
    }
}
