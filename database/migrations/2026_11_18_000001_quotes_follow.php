<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Seguimiento de presupuestos sin respuesta: cuándo se reclamó por última vez, cuántas veces y si ya no hay que insistir. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('quotes', 'followed_at')) {
            return;
        }
        Schema::table('quotes', function (Blueprint $t) {
            $t->timestamp('followed_at')->nullable();
            $t->unsignedTinyInteger('follow_n')->default(0);
            $t->boolean('follow_off')->default(false);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('quotes', 'followed_at')) {
            return;
        }
        Schema::table('quotes', function (Blueprint $t) {
            $t->dropColumn(['followed_at', 'follow_n', 'follow_off']);
        });
    }
};
