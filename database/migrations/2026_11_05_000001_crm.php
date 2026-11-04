<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clientes: los de FactuSOL llegan por sincronización (solo lectura); los demás se crean a mano.
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('codcli')->nullable()->unique(); // código en FactuSOL
            $t->string('name');
            $t->string('trade_name')->nullable();
            $t->string('nif', 30)->nullable()->index();
            $t->string('phone', 40)->nullable();
            $t->string('mobile', 40)->nullable();
            $t->string('email')->nullable()->index();
            $t->string('address')->nullable();
            $t->string('city')->nullable();
            $t->string('postcode', 12)->nullable();
            $t->string('province')->nullable();
            $t->text('factusol_notes')->nullable(); // observaciones de FactuSOL (solo lectura)
            $t->timestamp('factusol_created_at')->nullable();
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
        });

        // Notas propias del panel (no se pisan al sincronizar).
        Schema::create('customer_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->text('body');
            $t->timestamps();
        });

        // Flota: máquinas de cada cliente.
        Schema::create('machines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('type', 60)->nullable();   // miniexcavadora, dumper, bulldozer…
            $t->string('brand', 80)->nullable();
            $t->string('model', 120)->nullable();
            $t->unsignedSmallInteger('year')->nullable();
            $t->string('serial', 80)->nullable();
            $t->string('parts', 255)->nullable(); // medida de cadena, referencia de cuchilla…
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machines');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('customers');
    }
};
