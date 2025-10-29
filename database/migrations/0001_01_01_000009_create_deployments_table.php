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
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->foreignId('nation_id')->constrained('nations')->onDelete('cascade');
            $table->foreignId('territory_id')->constrained('territories')->onDelete('cascade');
            $table->foreignId('turn_id')->constrained('turns')->onDelete('cascade');
            $table->integer('division_type');
            $table->boolean('has_been_deployed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
