<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Artículos de FactuSOL (F_ART + stock F_STO + tarifa 1 F_LTA + familia F_FAM)
        Schema::create('articles', function (Blueprint $t) {
            $t->id();
            $t->string('codart', 60)->unique();
            $t->string('description')->nullable();
            $t->string('family_code', 20)->nullable()->index();
            $t->string('family', 80)->nullable();
            $t->decimal('cost', 12, 4)->default(0);     // PCOART
            $t->decimal('price', 12, 4)->default(0);    // tarifa 1 (PVP sin IVA)
            $t->decimal('margin', 6, 2)->nullable();    // margen de tarifa 1
            $t->string('ean', 40)->nullable()->index();
            $t->string('equivalent', 120)->nullable();   // EQUART
            $t->string('location', 60)->nullable();     // UBIART
            $t->decimal('stock', 12, 2)->default(0);    // ACTSTO
            $t->decimal('stock_available', 12, 2)->default(0); // DISSTO
            $t->decimal('stock_min', 12, 2)->default(0);
            $t->decimal('stock_max', 12, 2)->default(0);
            $t->date('factusol_created_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
        });

        // Proveedores de FactuSOL (F_PRO)
        Schema::create('fs_suppliers', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('codpro')->unique();
            $t->string('name');
            $t->string('trade_name')->nullable();
            $t->string('nif', 30)->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('email')->nullable();
            $t->string('web')->nullable();
            $t->string('address')->nullable();
            $t->string('city')->nullable();
            $t->string('postcode', 12)->nullable();
            $t->string('province')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
        });

        // Entradas de proveedor (F_ENT) se guardan en fs_docs con type = 'entrada'
        Schema::table('fs_docs', function (Blueprint $t) {
            $t->unsignedInteger('codpro')->nullable()->index()->after('codcli');
            $t->string('ext_ref', 80)->nullable()->after('ref'); // nº de albarán del proveedor
        });
    }

    public function down(): void
    {
        Schema::table('fs_docs', fn (Blueprint $t) => $t->dropColumn(['codpro', 'ext_ref']));
        Schema::dropIfExists('fs_suppliers');
        Schema::dropIfExists('articles');
    }
};
