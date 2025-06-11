<?php
// app/Models/Chat.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user1_id',
        'user2_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relación con el primer usuario
     */
    public function user1()
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    /**
     * Relación con el segundo usuario
     */
    public function user2()
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    /**
     * Relación con todos los mensajes del chat
     */
    public function messages()
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at', 'asc');
    }

    /**
     * Relación con el último mensaje del chat
     */
    public function latestMessage()
    {
        return $this->hasOne(ChatMessage::class)->latest();
    }

    /**
     * Obtener el otro usuario del chat (no el usuario actual)
     */
    public function getOtherUser($currentUserId)
    {
        return $this->user1_id === $currentUserId ? $this->user2 : $this->user1;
    }

    /**
     * Verificar si un usuario pertenece a este chat
     */
    public function hasUser($userId)
    {
        return $this->user1_id === $userId || $this->user2_id === $userId;
    }
}