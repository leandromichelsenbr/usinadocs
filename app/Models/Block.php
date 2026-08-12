<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Block extends Model
{
    protected $fillable = ['type', 'position', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn (self $block) => $block->assertRevisionIsEditable());
        static::deleting(fn (self $block) => $block->assertRevisionIsEditable());
    }

    public function pageRevision(): BelongsTo
    {
        return $this->belongsTo(PageRevision::class);
    }

    private function assertRevisionIsEditable(): void
    {
        if ($this->pageRevision()->value('status') === PageRevision::PUBLISHED) {
            throw new LogicException('Blocks in a published revision are immutable.');
        }
    }
}
