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
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('mode', ['online', 'offline', 'both'])->default('offline');
            $table->text('description')->nullable();
            $table->string('icon_path')->nullable();
            $table->boolean('is_multiplayer')->default(false); // Para saber si permite más de un jugador
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
