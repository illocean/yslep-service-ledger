<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_apostolate_entries', function (Blueprint $table): void {
            $table->id();
            $table->date('served_on')->nullable()->index();
            $table->string('about')->nullable();
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->unsignedInteger('source_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_apostolate_entries');
    }
};
