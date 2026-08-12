<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('native_name');
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'slug']);
        });

        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('status')->default('draft');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['page_id', 'number']);
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_revision_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('position');
            $table->json('data');
            $table->timestamps();
            $table->unique(['page_revision_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('page_revisions');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('sites');
    }
};
