<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'portal_tour_at')) {
            Schema::table('customers', fn (Blueprint $t) => $t->timestamp('portal_tour_at')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('portal_tour_at'));
    }
};
