<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Manuales: términos del glosario inglés → español del gremio añadidos o corregidos desde el panel. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('manual_terms')) {
            Schema::create('manual_terms', function (Blueprint $t) {
                $t->id();
                $t->string('en', 80)->unique();
                $t->string('es', 120);
                $t->string('type', 4)->default('nm');
                $t->foreignId('user_id')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_terms');
    }
};
