<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('machines', 'hours')) {
            Schema::table('machines', function (Blueprint $t) {
                $t->unsignedInteger('hours')->nullable();            // cuentahoras actual
                $t->date('hours_at')->nullable();                    // fecha de la última lectura
                $t->unsignedInteger('service_interval')->nullable(); // horas entre mantenimientos
                $t->unsignedInteger('last_service_hours')->nullable();
                $t->date('last_service_at')->nullable();
                $t->string('location', 160)->nullable();            // obra / ubicación
                $t->date('insurance_until')->nullable();
                $t->date('inspection_until')->nullable();
            });
        }
        if (! Schema::hasTable('machine_logs')) {
            Schema::create('machine_logs', function (Blueprint $t) {
                $t->id();
                $t->foreignId('machine_id')->constrained()->cascadeOnDelete();
                $t->string('kind', 20); // horas | mantenimiento | averia | nota
                $t->unsignedInteger('hours')->nullable();
                $t->date('date');
                $t->string('text', 1000)->nullable();
                $t->string('source', 10)->default('portal'); // portal | panel
                $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
            });
        }
        if (! Schema::hasColumn('customers', 'portal_failed')) {
            Schema::table('customers', function (Blueprint $t) {
                $t->unsignedTinyInteger('portal_failed')->default(0);
                $t->timestamp('portal_locked_until')->nullable();
            });
        }
        if (! Schema::hasTable('portal_logins')) {
            Schema::create('portal_logins', function (Blueprint $t) {
                $t->id();
                $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $t->string('ip', 64)->nullable();
                $t->string('agent', 200)->nullable();
                $t->boolean('ok')->default(true);
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_logins');
        Schema::dropIfExists('machine_logs');
    }
};
