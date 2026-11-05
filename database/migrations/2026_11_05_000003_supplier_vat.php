<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $t) {
            // 'sin' = importes sin IVA (base imponible), 'con' = con IVA incluido
            $t->string('vat', 3)->default('sin')->after('account');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $t) {
            $t->dropColumn('vat');
        });
    }
};
