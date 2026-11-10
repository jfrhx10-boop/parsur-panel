<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panel_entradas', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('entry_code');
            $t->unsignedInteger('codpro');
            $t->decimal('total', 12, 2)->default(0);
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamp('removed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_entradas');
    }
};
