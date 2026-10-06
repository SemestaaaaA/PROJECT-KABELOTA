<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('talent_id')->constrained('talents')->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->string('status')->default('baru'); // baru | ditinjau | diterima | ditolak
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();

            $table->unique(['job_posting_id', 'talent_id']);
        });

        Schema::table('recruitment_offers', function (Blueprint $table) {
            $table->string('response_note')->nullable()->after('responded_at');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_offers', fn (Blueprint $t) => $t->dropColumn('response_note'));
        Schema::dropIfExists('job_applications');
    }
};
