<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
 public function up(): void {
  Schema::table('languages', fn(Blueprint $t) => $t->string('route_key', 10)->nullable()->unique()->after('code'));
  Schema::create('page_localizations', function(Blueprint $t) { $t->id(); $t->foreignId('page_id')->constrained()->cascadeOnDelete(); $t->foreignId('language_id')->constrained()->restrictOnDelete(); $t->string('slug'); $t->unsignedBigInteger('published_revision_id')->nullable(); $t->timestamps(); $t->unique(['page_id','language_id']); $t->unique(['language_id','slug']); });
  DB::table('languages')->orderBy('id')->each(function ($language) { DB::table('languages')->where('id',$language->id)->update(['route_key' => str_starts_with($language->code,'pt') ? 'pt' : $language->code]); });
  DB::table('pages')->orderBy('id')->each(function ($page) { $revision = DB::table('page_revisions')->where('id',$page->published_revision_id)->first() ?: DB::table('page_revisions')->where('page_id',$page->id)->orderBy('number')->first(); if ($revision) DB::table('page_localizations')->insert(['page_id'=>$page->id,'language_id'=>$revision->language_id,'slug'=>$page->slug,'published_revision_id'=>$page->published_revision_id,'created_at'=>now(),'updated_at'=>now()]); });
 }
 public function down(): void { Schema::dropIfExists('page_localizations'); Schema::table('languages', fn(Blueprint $t) => $t->dropUnique(['route_key'])); Schema::table('languages', fn(Blueprint $t) => $t->dropColumn('route_key')); }
};
