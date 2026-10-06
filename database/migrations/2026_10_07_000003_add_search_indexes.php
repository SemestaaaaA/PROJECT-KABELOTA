<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Indexes for the /talenta filters and the open-jobs scope.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talents', function (Blueprint $table) {
            $table->index(['is_visible', 'type']);
            $table->index('city');
            $table->index('experience_since');
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->index(['status', 'closes_at']);
        });

        Schema::table('recruitment_offers', function (Blueprint $table) {
            $table->index(['talent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('talents', function (Blueprint $table) {
            $table->dropIndex(['is_visible', 'type']);
            $table->dropIndex(['city']);
            $table->dropIndex(['experience_since']);
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropIndex(['status', 'closes_at']);
        });

        Schema::table('recruitment_offers', function (Blueprint $table) {
            $table->dropIndex(['talent_id', 'status']);
        });
    }
};
