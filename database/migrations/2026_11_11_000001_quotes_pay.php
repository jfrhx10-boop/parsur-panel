<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $t) {
            $t->unsignedBigInteger('pay_order_id')->nullable(); // pedido de la tienda creado para cobrar este presupuesto
            $t->text('pay_url')->nullable();                    // enlace de pago con tarjeta
            $t->decimal('pay_total', 12, 2)->nullable();        // importe con el que se creó el enlace
            $t->string('pay_status', 30)->nullable();           // estado del pedido de la tienda (pending, processing…)
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $t) {
            $t->dropColumn(['pay_order_id', 'pay_url', 'pay_total', 'pay_status']);
        });
    }
};
