<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_group_items', function (Blueprint $table) {
            $table->unsignedBigInteger('source_entry_id')->nullable()->after('index_type');
            $table->unique(['index_type', 'source_entry_id'], 'report_group_items_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('report_group_items', function (Blueprint $table) {
            $table->dropUnique('report_group_items_source_unique');
            $table->dropColumn('source_entry_id');
        });
    }
};
