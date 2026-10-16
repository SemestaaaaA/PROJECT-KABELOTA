<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin overrides for marketing copy. html null means "show the default from the Blade view".
        Schema::create('site_texts', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->text('html')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One row per change: the html that was replaced (null when the default was showing).
        Schema::create('site_text_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->index();
            $table->text('html')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_text_revisions');
        Schema::dropIfExists('site_texts');
    }
};
