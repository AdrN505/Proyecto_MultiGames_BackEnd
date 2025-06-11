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
        Schema::create('game_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_code', 8)->unique(); // Código único de 6-8 caracteres
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->foreignId('host_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('guest_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->enum('status', ['waiting', 'playing', 'finished'])->default('waiting');
            $table->enum('turn', ['host', 'guest'])->default('host'); // De quién es el turno
            $table->json('settings')->nullable(); // Configuraciones del juego
            $table->json('game_state')->nullable(); // Estado actual del juego
            $table->timestamp('last_activity_at')->useCurrent(); // Para limpiar salas inactivas
            $table->timestamps();
            
            // Índices para optimizar búsquedas
            $table->index(['game_id', 'status']);
            $table->index('room_code');
            $table->index('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_rooms');
    }
};