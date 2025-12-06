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
        Schema::create('leader_details', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->foreignId('nation_id')->constrained('nations')->onDelete('cascade');
            $table->foreignId('leader_id')->constrained('leaders')->onDelete('cascade');
            $table->foreignId('turn_id')->constrained('turns')->onDelete('cascade');
            $table->text('name');
            $table->text('title');
            $table->text('picture_src')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leader_details');
    }
};
