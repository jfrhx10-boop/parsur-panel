<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fs_doc_lines', 'pending')) {
            Schema::table('fs_doc_lines', fn (Blueprint $t) => $t->decimal('pending', 10, 2)->nullable()->after('qty'));
        }
        if (! Schema::hasTable('order_line_receipts')) {
            // Validación en el panel: cantidad de cada línea de pedido que ya ha llegado al almacén (aunque aún no se haya servido).
            Schema::create('order_line_receipts', function (Blueprint $t) {
                $t->id();
                $t->foreignId('doc_id')->constrained('fs_docs')->cascadeOnDelete();
                $t->unsignedSmallInteger('pos');
                $t->decimal('received', 10, 2)->default(0);
                $t->string('note', 200)->nullable();
                $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->unique(['doc_id', 'pos']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_line_receipts');
        Schema::table('fs_doc_lines', fn (Blueprint $t) => $t->dropColumn('pending'));
    }
};
