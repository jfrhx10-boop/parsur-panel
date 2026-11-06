<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'search_name')) {
            Schema::table('customers', function (Blueprint $t) {
                $t->string('search_name', 255)->nullable()->index(); // nombre normalizado (sin tildes, mayúsculas)
                $t->text('search')->nullable();                      // todo lo buscable normalizado
            });
        }
        DB::table('customers')->select('id', 'name', 'trade_name', 'nif', 'phone', 'mobile', 'email', 'city', 'province')->orderBy('id')->chunk(300, function ($rows) {
            foreach ($rows as $r) {
                DB::table('customers')->where('id', $r->id)->update(\App\Models\Customer::searchFields((array) $r));
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn(['search_name', 'search']));
    }
};
