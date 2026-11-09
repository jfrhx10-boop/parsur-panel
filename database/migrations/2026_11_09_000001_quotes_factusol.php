<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $t) {
            $t->integer('fs_year')->nullable();
            $t->integer('fs_code')->nullable();
        });
    }

    public function down(): void {}
};
