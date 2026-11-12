<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('machine_checks')) {
            Schema::create('machine_checks', function (Blueprint $t) {
                $t->id();
                $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $t->foreignId('machine_id')->constrained()->cascadeOnDelete();
                $t->date('date');
                $t->unsignedInteger('hours')->nullable();
                $t->text('items');                       // JSON: punto => ok | mal
                $t->unsignedTinyInteger('faults')->default(0);
                $t->string('by', 120)->nullable();       // quién la ha hecho
                $t->text('notes')->nullable();
                $t->text('photos')->nullable();          // JSON: rutas privadas
                $t->timestamps();
                $t->index(['machine_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_checks');
    }
};
