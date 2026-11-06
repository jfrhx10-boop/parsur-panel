<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('articles', 'search')) {
            Schema::table('articles', function (Blueprint $t) {
                $t->text('search')->nullable();
                $t->string('category', 30)->nullable()->index();
            });
        }
        // Relleno inicial (la sincronización del catálogo lo mantiene después)
        DB::table('articles')->select('id', 'codart', 'description', 'equivalent', 'ean')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $r) {
                DB::table('articles')->where('id', $r->id)->update([
                    'search' => \App\Services\ArticleCategories::searchText($r->codart, $r->description, $r->equivalent, $r->ean),
                    'category' => \App\Services\ArticleCategories::of($r->description),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('articles', fn (Blueprint $t) => $t->dropColumn(['search', 'category']));
    }
};
