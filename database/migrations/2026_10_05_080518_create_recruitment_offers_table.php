<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('talent_id')->constrained('talents')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('position');
            $table->date('start_date')->nullable();
            $table->string('duration')->nullable();
            $table->text('message');
            $table->string('status')->default('menunggu'); // menunggu | diterima | ditolak
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_offers');
    }
};
