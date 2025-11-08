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
        Schema::create('game_shared_static_assets', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->text('src');
            $table->integer('type');
            $table->mediumText('title');
            $table->mediumText('description');
            $table->mediumText('attribution');
            $table->foreignId('lessee_nation_id')->nullable()->constrained('nations')->nullOnDelete();
            $table->timestamp('leased_at')->nullable();
            $table->foreignId('holder_nation_id')->nullable()->constrained('nations')->nullOnDelete();
            $table->timestamp('held_until')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_shared_static_assets');
    }
};
