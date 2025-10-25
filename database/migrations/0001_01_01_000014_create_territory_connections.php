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
        Schema::create('territory_connections', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->foreignId('territory_id')->constrained('territories')->onDelete('cascade');
            $table->foreignId('connected_territory_id')->constrained('territories')->onDelete('cascade');
            $table->boolean('is_connected_by_land');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('territory_connections');
    }
};
