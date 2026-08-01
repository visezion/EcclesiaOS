<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Utf8Text;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BibleTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id', 'created_by', 'name', 'abbreviation', 'language', 'description',
        'copyright', 'source_url', 'status', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function getNameAttribute(?string $value): ?string
    {
        return Utf8Text::repair($value);
    }

    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = Utf8Text::repair($value);
    }

    public function getLanguageAttribute(?string $value): ?string
    {
        return Utf8Text::repair($value);
    }

    public function setLanguageAttribute(?string $value): void
    {
        $this->attributes['language'] = Utf8Text::repair($value);
    }

    public function getDescriptionAttribute(?string $value): ?string
    {
        return Utf8Text::repair($value);
    }

    public function setDescriptionAttribute(?string $value): void
    {
        $this->attributes['description'] = Utf8Text::repair($value);
    }

    public function getCopyrightAttribute(?string $value): ?string
    {
        return Utf8Text::repair($value);
    }

    public function setCopyrightAttribute(?string $value): void
    {
        $this->attributes['copyright'] = Utf8Text::repair($value);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verses(): HasMany
    {
        return $this->hasMany(BibleVerse::class);
    }
}
