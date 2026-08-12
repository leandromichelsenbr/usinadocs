<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
class PageLocalization extends Model { protected $fillable=['page_id','language_id','slug','published_revision_id']; public function page(): BelongsTo{return $this->belongsTo(Page::class);} public function language(): BelongsTo{return $this->belongsTo(Language::class);} public function publishedRevision(): BelongsTo{return $this->belongsTo(PageRevision::class,'published_revision_id');} public function latestDraft(): HasOne{return $this->hasOne(PageRevision::class,'page_id','page_id')->where('language_id',$this->language_id)->whereIn('status',[PageRevision::DRAFT,PageRevision::IN_REVIEW])->ofMany('number','max');} }
