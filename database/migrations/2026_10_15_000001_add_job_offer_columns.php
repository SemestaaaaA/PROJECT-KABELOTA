<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Final job offers: a company offers a position, the talent takes one, every other open process closes.
return new class extends Migration
{
    private const TABLES = ['recruitment_offers', 'job_applications'];

    private const COLUMNS = [
        'job_offer_position', 'job_offer_start_date', 'job_offer_note', 'job_offered_at',
        'job_offer_expires_at', 'job_offer_reminded_at', 'closed_at', 'closed_note',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('job_offer_position')->nullable();
                $table->date('job_offer_start_date')->nullable();
                $table->text('job_offer_note')->nullable();
                $table->timestamp('job_offered_at')->nullable();
                $table->timestamp('job_offer_expires_at')->nullable();
                $table->timestamp('job_offer_reminded_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->text('closed_note')->nullable();
            });
        }

        $this->convertAcceptedApplications();
    }

    /** Applications a company marked "Diterima" before this change now wait for the talent's answer. */
    public function convertAcceptedApplications(): void
    {
        DB::table('job_applications')->where('status', 'diterima')->update([
            'status' => 'tawaran_kerja',
            'job_offer_position' => DB::raw('(select title from job_postings where job_postings.id = job_applications.job_posting_id)'),
            'job_offered_at' => now(),
            'job_offer_expires_at' => now()->addDays(7),
        ]);
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            // Lossy on purpose: the old schema only knew diterima/ditolak.
            DB::table($name)->whereIn('status', ['tawaran_kerja', 'diambil'])->update(['status' => 'diterima']);
            DB::table($name)->whereIn('status', ['ditutup', 'kedaluwarsa'])->update(['status' => 'ditolak']);
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(self::COLUMNS));
        }
    }
};
