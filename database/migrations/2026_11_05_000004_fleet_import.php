<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $t) {
            $t->text('parts')->nullable()->change();            // piezas y filtros apuntados (antes 255 caracteres)
            $t->string('source', 20)->default('manual')->after('notes'); // manual | import
            $t->boolean('review')->default(false)->after('source');      // importada y por revisar
        });
        Schema::table('customers', function (Blueprint $t) {
            $t->timestamp('fleet_imported_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('fleet_imported_at'));
        Schema::table('machines', fn (Blueprint $t) => $t->dropColumn(['source', 'review']));
    }
};
