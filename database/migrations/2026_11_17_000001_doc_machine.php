<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Máquina de un presupuesto o pedido: de la flota del cliente (machine_id) o apuntada a mano y pendiente de añadir a la flota. */
return new class extends Migration
{
    private array $cols = ['machine_type', 'machine_brand', 'machine_model', 'machine_serial', 'machine_year', 'machine_hours'];

    public function up(): void
    {
        foreach (['quotes', 'fs_docs'] as $table) {
            $needId = ! Schema::hasColumn($table, 'machine_id');
            $needCols = ! Schema::hasColumn($table, 'machine_model');
            if (! $needId && ! $needCols) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($needId, $needCols) {
                if ($needId) {
                    $t->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
                }
                if ($needCols) {
                    $t->string('machine_type', 60)->nullable();
                    $t->string('machine_brand', 80)->nullable();
                    $t->string('machine_model', 120)->nullable();
                    $t->string('machine_serial', 80)->nullable();
                    $t->unsignedSmallInteger('machine_year')->nullable();
                    $t->unsignedInteger('machine_hours')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['quotes', 'fs_docs'] as $table) {
            if (Schema::hasColumn($table, 'machine_model')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($this->cols));
            }
        }
        if (Schema::hasColumn('fs_docs', 'machine_id')) {
            Schema::table('fs_docs', fn (Blueprint $t) => $t->dropConstrainedForeignId('machine_id'));
        }
    }
};
