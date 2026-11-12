<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'offers_at')) {
            Schema::table('customers', function (Blueprint $t) {
                // Consentimiento para recibir ofertas por correo: cuándo lo dio (o null), cuándo lo retiró y desde dónde
                $t->timestamp('offers_at')->nullable();
                $t->timestamp('offers_off_at')->nullable();
                $t->string('offers_src', 40)->nullable();
            });
        }
    }

    public function down(): void
    {
    }
};
