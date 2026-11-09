<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panel_albaranes', function (Blueprint $t) {
            $t->id();
            $t->integer('year');
            $t->integer('albaran_code');
            $t->integer('pedido_code');
            $t->text('lines'); // json: pos => cantidad servida
            $t->decimal('total', 12, 2)->default(0);
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamp('removed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_albaranes');
    }
};
