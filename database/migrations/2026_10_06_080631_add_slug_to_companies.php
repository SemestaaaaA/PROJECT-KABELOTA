<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->string('slug')->nullable()->unique()->after('id'));

        foreach (DB::table('companies')->get() as $c) {
            DB::table('companies')->where('id', $c->id)->update(['slug' => Str::slug($c->name).'-'.$c->id]);
        }
    }

    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('slug'));
    }
};
