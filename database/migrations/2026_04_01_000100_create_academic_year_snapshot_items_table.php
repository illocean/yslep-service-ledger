<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_year_snapshot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('report_group_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_report_tag')->nullable();
            $table->string('source_report_label')->nullable();
            $table->string('index_type');
            $table->date('served_on');
            $table->time('time_start');
            $table->time('time_end');
            $table->string('cycle_code')->nullable();
            $table->string('module_code')->nullable();
            $table->string('title')->nullable();
            $table->string('about')->nullable();
            $table->unsignedInteger('source_order')->default(0);
            $table->timestamps();

            $table->index(['academic_year_snapshot_id', 'index_type'], 'academic_year_snapshot_items_index_type');
            $table->unique(['academic_year_snapshot_id', 'report_group_item_id'], 'academic_year_snapshot_items_unique_report_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_year_snapshot_items');
    }
};
