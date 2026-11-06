<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Presupuestos hechos desde el panel (se pasarán a FactuSOL cuando esté la escritura)
        Schema::create('quotes', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();          // P-2026-0001
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $t->string('ref', 120)->nullable();           // máquina / referencia
            $t->date('date');
            $t->date('valid_until')->nullable();
            $t->string('status', 12)->default('borrador'); // borrador | enviado | aceptado | rechazado
            $t->decimal('iva_pct', 5, 2)->default(21);
            $t->decimal('base', 12, 2)->default(0);
            $t->decimal('iva', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->text('notes')->nullable();
            $t->unsignedInteger('factusol_code')->nullable(); // cuando se pase a FactuSOL
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('quote_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('pos')->default(0);
            $t->string('art', 60)->nullable();
            $t->string('description')->nullable();
            $t->decimal('qty', 10, 2)->default(1);
            $t->decimal('price', 12, 4)->default(0);
            $t->decimal('discount', 5, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
        });
        Schema::table('machines', function (Blueprint $t) {
            $t->text('parts')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_lines');
        Schema::dropIfExists('quotes');
    }
};
