<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('talent_id')->constrained('talents')->cascadeOnDelete();
            $table->string('name');
            $table->string('location');
            $table->string('client');
            $table->string('contractor');
            $table->string('position');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('year_start');
            $table->unsignedSmallInteger('year_end')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
