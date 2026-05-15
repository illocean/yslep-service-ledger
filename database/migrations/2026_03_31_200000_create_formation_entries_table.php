<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formation_entries', function (Blueprint $table): void {
            $table->id();
            $table->date('served_on')->nullable()->index();
            $table->string('cycle_code', 20)->nullable();
            $table->string('module_code', 20)->nullable();
            $table->string('title')->nullable();
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->unsignedInteger('source_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formation_entries');
    }
};
