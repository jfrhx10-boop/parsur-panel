<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fs_docs', function (Blueprint $t) {
            $t->index(['type', 'date'], 'fs_docs_type_date');
            $t->index(['type', 'codcli'], 'fs_docs_type_codcli');
            $t->index(['type', 'status'], 'fs_docs_type_status');
            $t->index(['type', 'year', 'code'], 'fs_docs_type_year_code');
        });
        Schema::table('fs_doc_lines', function (Blueprint $t) {
            $t->index(['doc_id', 'art'], 'fs_doc_lines_doc_art');
        });
        Schema::table('articles', function (Blueprint $t) {
            $t->index('stock');
            $t->index('description');
        });
        Schema::table('machines', function (Blueprint $t) {
            $t->index('serial');
            $t->index('brand');
        });
    }

    public function down(): void
    {
        Schema::table('fs_docs', function (Blueprint $t) {
            $t->dropIndex('fs_docs_type_date');
            $t->dropIndex('fs_docs_type_codcli');
            $t->dropIndex('fs_docs_type_status');
            $t->dropIndex('fs_docs_type_year_code');
        });
        Schema::table('fs_doc_lines', fn (Blueprint $t) => $t->dropIndex('fs_doc_lines_doc_art'));
    }
};
