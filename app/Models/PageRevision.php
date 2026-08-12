<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PageRevision extends Model
{
    public const DRAFT = 'draft';
    public const IN_REVIEW = 'in_review';
    public const PUBLISHED = 'published';

    protected $fillable = ['language_id', 'number', 'status', 'title', 'summary', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $revision): void {
            if ($revision->getOriginal('status') === self::PUBLISHED) {
                throw new LogicException('A published revision is immutable. Create a new revision instead.');
            }
        });
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class)->orderBy('position');
    }
}
