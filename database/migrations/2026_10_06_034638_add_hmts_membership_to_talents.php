<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talents', function (Blueprint $table) {
            // Not a member (or not chosen) = pasif.
            $table->string('hmts_status')->default('pasif')->after('skills');
            $table->string('hmts_position')->nullable()->after('hmts_status');
        });
    }

    public function down(): void
    {
        Schema::table('talents', fn (Blueprint $t) => $t->dropColumn(['hmts_status', 'hmts_position']));
    }
};
