<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 20);          // pago | descuento | facturacion | aviso
            $t->string('text', 500);
            $t->string('source', 12)->default('panel'); // factusol | panel
            $t->boolean('active')->default(true);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('payment_terms', function (Blueprint $t) {
            $t->unsignedSmallInteger('code')->primary();
            $t->string('name', 120);
            $t->unsignedSmallInteger('days')->default(0);
        });
        Schema::table('customers', function (Blueprint $t) {
            $t->unsignedSmallInteger('payment_code')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('payment_code'));
        Schema::dropIfExists('payment_terms');
        Schema::dropIfExists('customer_alerts');
    }
};
