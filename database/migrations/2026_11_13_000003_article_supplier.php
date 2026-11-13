<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Proveedor habitual del artículo (F_ART.PHAART en FactuSOL). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('articles', 'codpro')) {
            Schema::table('articles', function (Blueprint $t) {
                $t->unsignedInteger('codpro')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('articles', 'codpro')) {
            Schema::table('articles', fn (Blueprint $t) => $t->dropColumn('codpro'));
        }
    }
};
