<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('package'); // magang | reguler | tenaga_ahli
            $table->string('concentration')->nullable();
            $table->unsignedTinyInteger('min_jenjang')->nullable();
            $table->unsignedTinyInteger('min_experience')->nullable();
            $table->unsignedTinyInteger('duration_months');
            $table->string('location');
            $table->text('description');
            $table->date('closes_at');
            $table->unsignedSmallInteger('applicants_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
