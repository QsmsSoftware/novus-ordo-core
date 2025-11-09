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
        Schema::create('asset_infos', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->text('src')->unique();
            $table->mediumText('title')->nullable();
            $table->mediumText('description')->nullable();
            $table->mediumText('attribution')->nullable();
            $table->mediumText('license')->nullable();
            $table->mediumText('license_uri')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_infos');
    }
};
