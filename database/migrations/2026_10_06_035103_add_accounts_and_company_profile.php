<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('talenta')->after('email'); // talenta | perusahaan | admin
        });

        Schema::table('talents', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained()->nullOnDelete();
            $table->string('status')->default('menunggu')->after('city'); // menunggu | terverifikasi | ditolak
            $table->string('rejection_reason')->nullable()->after('status');
            $table->string('nib')->nullable()->after('rejection_reason');
            $table->string('website')->nullable()->after('nib');
            $table->text('about')->nullable()->after('website');
            $table->string('contact_name')->nullable()->after('about');
            $table->string('contact_phone')->nullable()->after('contact_name');
            $table->string('logo_path')->nullable()->after('contact_phone');
            $table->string('legal_doc_path')->nullable()->after('logo_path');
        });

        Schema::table('recruitment_offers', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('talent_id')->constrained()->nullOnDelete();
            $table->timestamp('responded_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_offers', function (Blueprint $t) { $t->dropConstrainedForeignId('company_id'); $t->dropColumn('responded_at'); });
        Schema::table('companies', function (Blueprint $t) {
            $t->dropConstrainedForeignId('user_id');
            $t->dropColumn(['status', 'rejection_reason', 'nib', 'website', 'about', 'contact_name', 'contact_phone', 'logo_path', 'legal_doc_path']);
        });
        Schema::table('talents', fn (Blueprint $t) => $t->dropConstrainedForeignId('user_id'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role'));
    }
};
