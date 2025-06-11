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
        // Tabla para las conversaciones entre usuarios
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user1_id'); // Usuario que inició el chat
            $table->unsignedBigInteger('user2_id'); // Usuario con el que se chatea
            $table->timestamp('last_message_at')->nullable(); // Timestamp del último mensaje
            $table->timestamps();

            // Claves foráneas
            $table->foreign('user1_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user2_id')->references('id')->on('users')->onDelete('cascade');

            // Índice único para evitar chats duplicados
            $table->unique(['user1_id', 'user2_id']);
            
            // Índices para mejorar el rendimiento
            $table->index(['user1_id', 'last_message_at']);
            $table->index(['user2_id', 'last_message_at']);
        });

        // Tabla para los mensajes del chat
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_id');
            $table->unsignedBigInteger('sender_id'); // Usuario que envía el mensaje
            $table->text('message'); // Contenido del mensaje
            $table->boolean('is_read')->default(false); // Si el mensaje ha sido leído
            $table->timestamp('read_at')->nullable(); // Cuándo fue leído
            $table->timestamps();

            // Claves foráneas
            $table->foreign('chat_id')->references('id')->on('chats')->onDelete('cascade');
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');

            // Índices para mejorar el rendimiento
            $table->index(['chat_id', 'created_at']);
            $table->index(['sender_id', 'created_at']);
            $table->index(['is_read', 'chat_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chats');
    }
};
