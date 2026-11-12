<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $t) {
            $t->string('pay_note', 300)->nullable(); // qué se hizo al cobrarse por la web (pedido creado o el motivo por el que no)
        });
        Schema::create('web_order_imports', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->unique(); // pedido de WooCommerce
            $t->string('number', 40)->nullable();
            $t->string('result', 30);                     // ok, sin_cliente, revisar, error, omitido
            $t->string('note', 300)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $t) {
            $t->dropColumn('pay_note');
        });
        Schema::dropIfExists('web_order_imports');
    }
};
