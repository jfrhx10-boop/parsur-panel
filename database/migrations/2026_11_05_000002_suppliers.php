<?php

use App\Services\SupplierImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code', 30)->nullable()->unique();
            $t->string('account', 60)->nullable();      // nuestro número de cliente en el proveedor
            $t->decimal('ytd_prev', 12, 2)->nullable();  // importe del año anterior hasta la misma fecha (según el proveedor)
            $t->string('source')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('imported_at')->nullable();
            $t->timestamps();
        });

        Schema::create('supplier_references', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $t->string('ref', 80);
            $t->string('family', 20)->nullable()->index(); // prefijo de la referencia (SA, SH, SN…)
            $t->string('label')->nullable();               // descripción, editable más adelante
            $t->timestamps();
            $t->unique(['supplier_id', 'ref']);
        });

        Schema::create('supplier_consumption', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_reference_id')->constrained('supplier_references')->cascadeOnDelete();
            $t->unsignedSmallInteger('year');
            $t->integer('quantity')->default(0);
            $t->decimal('amount', 12, 2)->default(0);
            $t->unique(['supplier_reference_id', 'year']);
        });

        // Carga inicial del histórico de HIFI (si el archivo de datos está en el repositorio).
        try {
            $file = database_path('data/hifi_consumo.json');
            if (is_file($file)) {
                (new SupplierImporter())->importJson($file);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_consumption');
        Schema::dropIfExists('supplier_references');
        Schema::dropIfExists('suppliers');
    }
};
