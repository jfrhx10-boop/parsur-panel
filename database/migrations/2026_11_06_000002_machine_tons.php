<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('machines', 'tons')) {
            Schema::table('machines', fn (Blueprint $t) => $t->decimal('tons', 6, 1)->nullable()->after('year'));
        }
    }

    public function down(): void
    {
        Schema::table('machines', fn (Blueprint $t) => $t->dropColumn('tons'));
    }
};
