<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('machines', 'site')) {
            Schema::table('machines', function (Blueprint $t) {
                $t->string('site', 160)->nullable();        // obra
                $t->string('site_client', 160)->nullable(); // para qué cliente trabaja
            });
        }
        if (! Schema::hasTable('machine_reminders')) {
            Schema::create('machine_reminders', function (Blueprint $t) {
                $t->id();
                $t->foreignId('machine_id')->constrained()->cascadeOnDelete();
                $t->string('text', 300);
                $t->date('due_date')->nullable();
                $t->unsignedInteger('due_hours')->nullable();
                $t->timestamp('done_at')->nullable();
                $t->string('source', 10)->default('portal');
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_reminders');
    }
};
