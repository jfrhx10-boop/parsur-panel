<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('machines', 'photo')) {
            Schema::table('machines', fn (Blueprint $t) => $t->string('photo', 255)->nullable());
        }
        if (! Schema::hasColumn('portal_orders', 'photo')) {
            Schema::table('portal_orders', fn (Blueprint $t) => $t->string('photo', 255)->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('machines', fn (Blueprint $t) => $t->dropColumn('photo'));
        Schema::table('portal_orders', fn (Blueprint $t) => $t->dropColumn('photo'));
    }
};
