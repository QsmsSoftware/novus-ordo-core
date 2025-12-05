<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('labor_pool_allocations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('nation_id')->constrained('nations')->cascadeOnDelete();
            $table->foreignId('territory_id')->constrained('territories')->cascadeOnDelete();
            $table->foreignId('turn_id')->constrained('turns')->cascadeOnDelete();
            $table->foreignId('labor_pool_id')->constrained('labor_pools')->cascadeOnDelete();
            $table->foreignId('labor_pool_facility_id')->constrained('labor_pool_facilities')->cascadeOnDelete();
            $table->integer('resource_type');
            $table->bigInteger('allocation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labor_pool_allocations');
    }
};
