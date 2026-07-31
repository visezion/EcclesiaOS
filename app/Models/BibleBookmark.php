<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BibleBookmark extends Model
{
    protected $fillable = ['user_id', 'church_id', 'bible_translation_id', 'reference', 'book', 'chapter', 'verse', 'preview', 'tags'];

    protected function casts(): array
    {
        return ['tags' => 'array'];
    }

    public function translation()
    {
        return $this->belongsTo(BibleTranslation::class, 'bible_translation_id');
    }
}
