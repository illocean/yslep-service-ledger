<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_group_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_group_id')->constrained()->cascadeOnDelete();
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

            $table->index(['report_group_id', 'index_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_group_items');
    }
};
