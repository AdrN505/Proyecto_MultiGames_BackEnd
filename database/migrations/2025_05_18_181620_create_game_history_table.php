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
        Schema::create('game_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->enum('mode', ['online', 'offline'])->default('offline');
            $table->foreignId('opponent_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('opponent_type', ['human', 'ai', 'local'])->default('ai'); // Tipo de oponente
            $table->boolean('counts_for_stats')->default(true); // ¿Cuenta para estadísticas?
            $table->enum('result', ['won', 'lost', 'draw', 'abandoned'])->nullable();
            $table->integer('score')->default(0);
            $table->integer('points_earned')->default(0);
            $table->integer('points_lost')->default(0);
            $table->timestamps();
            
            // Índice para búsquedas frecuentes
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'game_id', 'mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_history');
    }
};
