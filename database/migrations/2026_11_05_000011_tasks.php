<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pendientes compartidos: los ven todos los usuarios del panel.
        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title', 300);
            $t->text('notes')->nullable();
            $t->date('due_date')->nullable()->index();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('done_at')->nullable()->index();
            $t->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
