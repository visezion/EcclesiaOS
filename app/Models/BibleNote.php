<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BibleNote extends Model
{
    protected $fillable = ['user_id', 'church_id', 'bible_translation_id', 'reference', 'title', 'body', 'tags', 'note_type'];

    protected function casts(): array
    {
        return ['tags' => 'array'];
    }

    public function translation()
    {
        return $this->belongsTo(BibleTranslation::class, 'bible_translation_id');
    }
}
