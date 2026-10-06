<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talents', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('type'); // alumni | mahasiswa
            $table->string('headline');
            $table->text('bio')->nullable();
            $table->string('concentration');
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('thesis_topic')->nullable();
            $table->decimal('gpa', 3, 2)->nullable();
            $table->string('city');
            $table->json('preferred_locations')->nullable();
            $table->string('availability')->default('tersedia');
            $table->unsignedSmallInteger('experience_since')->nullable();
            $table->string('email');
            $table->string('phone');
            $table->timestamps();

            $table->index(['availability', 'concentration']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talents');
    }
};
