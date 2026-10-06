<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talents', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('bio');
            $table->string('cv_path')->nullable()->after('photo_path');
            $table->string('skk_scan_path')->nullable()->after('cv_path');
            $table->string('transcript_path')->nullable()->after('skk_scan_path');
            $table->json('skills')->nullable()->after('transcript_path');
        });

        Schema::table('job_postings', function (Blueprint $table) {
            // menunggu_verifikasi -> aktif (admin approves the transfer proof)
            $table->string('status')->default('aktif')->after('package');
            $table->string('payment_proof_path')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('payment_proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('talents', fn (Blueprint $t) => $t->dropColumn(['photo_path', 'cv_path', 'skk_scan_path', 'transcript_path', 'skills']));
        Schema::table('job_postings', fn (Blueprint $t) => $t->dropColumn(['status', 'payment_proof_path', 'approved_at']));
    }
};
