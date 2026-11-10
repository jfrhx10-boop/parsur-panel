<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Albaranes de proveedor subidos al panel (PDF o foto), con lo leído de ellos
        Schema::create('supplier_notes', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('codpro')->nullable()->index();
            $t->string('supplier_name')->nullable();
            $t->string('their_no', 40)->nullable();
            $t->date('date')->nullable();
            $t->string('file');
            $t->string('file_name', 160)->nullable();
            $t->string('mime', 80)->nullable();
            $t->string('how', 10)->nullable();        // pdf | ocr | nada
            $t->longText('text')->nullable();
            $t->longText('lines')->nullable();        // JSON
            $t->timestamp('received_at')->nullable(); // llegada validada en los pedidos
            $t->unsignedSmallInteger('entry_year')->nullable();
            $t->unsignedInteger('entry_code')->nullable(); // entrada creada en FactuSOL
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
        });
        // Índices que faltaban (si alguno ya existiera, se sigue)
        foreach ([
            ['quotes', ['kind', 'date'], 'quotes_kind_date'], ['quotes', ['fs_year', 'fs_code'], 'quotes_fs'], ['quotes', ['customer_id'], 'quotes_customer'],
            ['quote_lines', ['quote_id'], 'quote_lines_quote'], ['quote_lines', ['art'], 'quote_lines_art'],
        ] as [$table, $cols, $name]) {
            try {
                Schema::table($table, fn (Blueprint $t) => $t->index($cols, $name));
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_notes');
    }
};
