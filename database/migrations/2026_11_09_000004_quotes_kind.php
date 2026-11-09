<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $t) {
            $t->string('kind', 12)->default('presupuesto'); // presupuesto | pedido | albaran (borradores que se crean directamente)
        });
    }

    public function down(): void {}
};
