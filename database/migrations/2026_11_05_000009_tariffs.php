<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariffs', function (Blueprint $t) {
            $t->unsignedTinyInteger('code')->primary();
            $t->string('name', 120);
            $t->decimal('margin', 6, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('article_prices', function (Blueprint $t) {
            $t->id();
            $t->string('codart', 60)->index();
            $t->unsignedTinyInteger('tariff');
            $t->decimal('margin', 6, 2)->default(0);
            $t->decimal('price', 12, 4)->default(0);
            $t->unique(['codart', 'tariff']);
        });
        Schema::table('customers', function (Blueprint $t) {
            $t->unsignedTinyInteger('tariff')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('tariff'));
        Schema::dropIfExists('article_prices');
        Schema::dropIfExists('tariffs');
    }
};
