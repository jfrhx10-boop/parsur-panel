<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quotes', 'customer_name')) {
            Schema::table('quotes', function (Blueprint $t) {
                // Presupuestos de FactuSOL sin ficha de cliente: solo llevan un nombre o un teléfono escrito a mano
                $t->string('customer_name', 190)->nullable();
            });
        }
    }

    public function down(): void
    {
    }
};
