<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Documentos de venta de FactuSOL: pedidos (F_PCL), albaranes (F_ALB) y facturas (F_FAC).
        Schema::create('fs_docs', function (Blueprint $t) {
            $t->id();
            $t->string('type', 10);                 // pedido | albaran | factura
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('code');
            $t->string('ref')->nullable();
            $t->date('date')->nullable()->index();
            $t->unsignedInteger('codcli')->nullable()->index();
            $t->string('customer_name')->nullable();
            $t->decimal('base', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->unsignedTinyInteger('status')->default(0); // estado tal cual lo da FactuSOL
            $t->unsignedInteger('order_code')->nullable(); // albarán: pedido del que viene
            $t->text('notes')->nullable();
            $t->unsignedSmallInteger('lines_count')->default(0);
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
            $t->unique(['type', 'year', 'code']);
        });
        Schema::create('fs_doc_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('doc_id')->constrained('fs_docs')->cascadeOnDelete();
            $t->unsignedSmallInteger('pos')->default(0);
            $t->string('art', 60)->nullable()->index();
            $t->string('description')->nullable();
            $t->decimal('qty', 10, 2)->default(0);
            $t->decimal('price', 10, 2)->default(0);
            $t->decimal('discount', 5, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_doc_lines');
        Schema::dropIfExists('fs_docs');
    }
};
