<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('talent_id')->constrained('talents')->cascadeOnDelete();
            $table->string('jabatan_kerja');
            $table->unsignedTinyInteger('jenjang');
            $table->string('registration_number');
            $table->date('issued_at');
            $table->date('expires_at');
            $table->timestamps();

            $table->index(['jabatan_kerja', 'jenjang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certifications');
    }
};
