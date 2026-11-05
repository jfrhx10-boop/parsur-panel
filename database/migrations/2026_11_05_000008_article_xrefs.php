<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Referencias equivalentes/compatibles sacadas de observaciones, equivalencia y descripción de cada artículo.
        Schema::create('article_xrefs', function (Blueprint $t) {
            $t->id();
            $t->string('codart', 60)->index();          // artículo de origen
            $t->string('ref', 80);                       // referencia tal como se apuntó
            $t->string('norm', 80)->index();             // normalizada (mayúsculas, sin espacios)
            $t->string('source', 12);                    // notas | equivalencia | descripcion
            $t->string('match_codart', 60)->nullable()->index(); // artículo del catálogo al que corresponde, si existe
            $t->unique(['codart', 'norm']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_xrefs');
    }
};
