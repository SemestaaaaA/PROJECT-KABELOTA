<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optimistic lock for Mode CMS: bumped on every write, compared with what the editor saw.
        // updated_at only has one-second precision, so two saves in the same second looked identical.
        Schema::table('site_texts', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(0)->after('html');
        });
    }

    public function down(): void
    {
        Schema::table('site_texts', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
