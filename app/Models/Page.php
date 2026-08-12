<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Page extends Model
{
    protected $fillable = ['site_id', 'slug'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class);
    }
    public function localizations(): HasMany { return $this->hasMany(PageLocalization::class); }

    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(PageRevision::class, 'published_revision_id');
    }

    public function latestDraft(): HasOne
    {
        return $this->hasOne(PageRevision::class)
            ->whereIn('status', [PageRevision::DRAFT, PageRevision::IN_REVIEW])
            ->ofMany('number', 'max');
    }
}
