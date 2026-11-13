<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fs_docs', 'paid')) {
            Schema::table('fs_docs', function (Blueprint $t) {
                $t->unsignedTinyInteger('paid')->nullable();   // albaranes: marca de cobrado de FactuSOL (COBALB)
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fs_docs', 'paid')) {
            Schema::table('fs_docs', fn (Blueprint $t) => $t->dropColumn('paid'));
        }
    }
};
