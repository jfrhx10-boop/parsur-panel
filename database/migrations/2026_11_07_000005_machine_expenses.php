<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('machine_expenses')) {
            Schema::create('machine_expenses', function (Blueprint $t) {
                $t->id();
                $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $t->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
                $t->date('date');
                $t->string('supplier', 160)->nullable();
                $t->string('concept', 300);
                $t->decimal('amount', 12, 2)->default(0);
                $t->string('file', 255)->nullable();      // factura adjunta (ruta privada)
                $t->string('file_name', 160)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_expenses');
    }
};
