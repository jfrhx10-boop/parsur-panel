<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Manuales: notas por página y equivalencias de referencias (solo consulta interna). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('manual_notes')) {
            Schema::create('manual_notes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('manual_id')->index();
                $t->unsignedInteger('page');
                $t->string('ref', 40)->nullable();      // referencia a la que se refiere la nota (normalizada), si la hay
                $t->text('body');
                $t->foreignId('user_id')->nullable();
                $t->timestamps();
                $t->index(['manual_id', 'page']);
            });
        }
        if (! Schema::hasTable('manual_equivs')) {
            Schema::create('manual_equivs', function (Blueprint $t) {
                $t->id();
                $t->string('ref', 40)->index();         // referencia del manual, normalizada
                $t->string('ref_text', 60);             // como se escribió
                $t->string('equiv', 40)->index();       // equivalente, normalizada
                $t->string('equiv_text', 60);
                $t->string('note')->nullable();
                $t->foreignId('user_id')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_equivs');
        Schema::dropIfExists('manual_notes');
    }
};
