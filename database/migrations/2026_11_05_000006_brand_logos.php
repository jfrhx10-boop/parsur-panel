<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Logos de marcas de maquinaria subidos desde Ajustes (se guardan en la base de datos
        // porque la carpeta pública del servidor no es escribible desde la aplicación).
        if (Schema::hasTable('brand_logos')) {
            return; // quedó creada en un intento anterior
        }
        Schema::create('brand_logos', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 60)->unique();
            $t->string('name', 80);
            $t->string('mime', 40);
            $t->binary('data');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_logos');
    }
};
