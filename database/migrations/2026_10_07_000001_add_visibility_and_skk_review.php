<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talents', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true)->after('availability');
            // Admin checks the SKK scan against the certifications listed in the profile.
            $table->timestamp('skk_verified_at')->nullable()->after('transcript_path');
            $table->string('skk_review_note')->nullable()->after('skk_verified_at');
        });

        Schema::table('certifications', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('talents', function (Blueprint $table) {
            $table->dropColumn(['is_visible', 'skk_verified_at', 'skk_review_note']);
        });

        Schema::table('certifications', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }
};
