<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BibleBookmark extends Model
{
    protected $fillable = ['user_id', 'church_id', 'bible_translation_id', 'reference', 'book', 'chapter', 'verse', 'preview', 'tags'];

    protected function casts(): array
    {
        return ['tags' => 'array'];
    }

    public function translation(): BelongsTo
    {
        return $this->belongsTo(BibleTranslation::class, 'bible_translation_id');
    }
}
