<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_notes', function (Blueprint $t) {
            $t->string('kind', 12)->default('albaran'); // albaran | factura
        });
        Schema::table('panel_entradas', function (Blueprint $t) {
            $t->string('kind', 12)->default('entrada'); // entrada | facturarec
        });
    }

    public function down(): void
    {
        Schema::table('supplier_notes', fn (Blueprint $t) => $t->dropColumn('kind'));
        Schema::table('panel_entradas', fn (Blueprint $t) => $t->dropColumn('kind'));
    }
};
