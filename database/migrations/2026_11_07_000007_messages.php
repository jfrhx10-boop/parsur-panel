<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $t) {
                $t->id();
                $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $t->string('from', 10); // cliente | parsur
                $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // quién respondió (parsur)
                $t->text('text');
                $t->string('photo', 255)->nullable();
                $t->string('context', 160)->nullable(); // de qué página venía (máquina, pedido…)
                $t->timestamp('read_at')->nullable();
                $t->timestamps();
                $t->index(['customer_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
