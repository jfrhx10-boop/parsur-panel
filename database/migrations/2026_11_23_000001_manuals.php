<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Manuales y catálogos técnicos (PDF) para uso interno, con el texto de cada página para buscar referencias. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('manuals')) {
            Schema::create('manuals', function (Blueprint $t) {
                $t->id();
                $t->string('title');
                $t->string('brand', 60)->nullable();
                $t->string('category', 80)->nullable();
                $t->text('notes')->nullable();
                $t->string('file');
                $t->string('file_name')->nullable();
                $t->unsignedBigInteger('size')->default(0);
                $t->unsignedInteger('pages')->default(0);
                $t->unsignedInteger('pages_read')->default(0);
                $t->unsignedInteger('refs')->default(0);
                $t->unsignedInteger('refs_in_fs')->default(0);
                $t->foreignId('user_id')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('manual_pages')) {
            Schema::create('manual_pages', function (Blueprint $t) {
                $t->id();
                $t->foreignId('manual_id')->index();
                $t->unsignedInteger('page');
                $t->string('label', 20)->nullable();   // número impreso en la página, si lo tiene
                $t->string('heading')->nullable();     // primeras líneas: sección / máquina
                $t->longText('text');
                $t->longText('norm');                  // texto en mayúsculas sin guiones ni espacios, para buscar referencias
                $t->text('refs')->nullable();          // referencias detectadas (normalizadas, separadas por espacio)
                $t->unique(['manual_id', 'page']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_pages');
        Schema::dropIfExists('manuals');
    }
};
