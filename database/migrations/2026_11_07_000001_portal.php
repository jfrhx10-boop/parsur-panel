<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'portal_token')) {
            Schema::table('customers', function (Blueprint $t) {
                $t->string('portal_token', 64)->nullable()->unique();
                $t->boolean('portal_enabled')->default(false);
                $t->string('portal_pin', 100)->nullable();
                $t->timestamp('portal_last_at')->nullable();
            });
        }
        if (! Schema::hasColumn('machines', 'operator')) {
            Schema::table('machines', function (Blueprint $t) {
                $t->string('operator', 120)->nullable();      // maquinista
                $t->string('operator_phone', 40)->nullable();
                $t->text('portal_notes')->nullable();
            });
        }
        if (! Schema::hasTable('portal_contacts')) {
            Schema::create('portal_contacts', function (Blueprint $t) {
                $t->id();
                $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $t->string('kind', 20)->default('maquinista'); // maquinista | proveedor | taller | otro
                $t->string('name', 120);
                $t->string('phone', 40)->nullable();
                $t->string('notes', 300)->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('portal_orders')) {
            Schema::create('portal_orders', function (Blueprint $t) {
                $t->id();
                $t->string('number', 20)->unique();
                $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $t->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
                $t->string('status', 20)->default('nuevo'); // nuevo | aceptado | servido | rechazado
                $t->text('notes')->nullable();
                $t->string('contact_name', 120)->nullable();
                $t->string('contact_phone', 40)->nullable();
                $t->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
                $t->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
                $t->timestamps();
            });
            Schema::create('portal_order_lines', function (Blueprint $t) {
                $t->id();
                $t->foreignId('portal_order_id')->constrained()->cascadeOnDelete();
                $t->string('label', 200);          // lo que ve el cliente ("Filtro aire primario")
                $t->string('ref', 80)->nullable();  // referencia interna (nunca se muestra en el portal)
                $t->decimal('qty', 10, 2)->default(1);
                $t->string('note', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_order_lines');
        Schema::dropIfExists('portal_orders');
        Schema::dropIfExists('portal_contacts');
    }
};
