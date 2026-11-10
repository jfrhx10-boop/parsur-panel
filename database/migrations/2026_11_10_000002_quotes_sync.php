<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $t) {
            $t->boolean('fs_dirty')->default(false); // hay cambios del panel pendientes de guardar en FactuSOL
            $t->text('fs_error')->nullable();        // último error al guardar en FactuSOL
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $t) {
            $t->dropColumn(['fs_dirty', 'fs_error']);
        });
    }
};
