<?php
// app/Models/ChatMessage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'sender_id',
        'message',
        'is_read'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relación con el chat al que pertenece el mensaje
     */
    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    /**
     * Relación con el usuario que envió el mensaje
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Scope para obtener mensajes no leídos
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope para obtener mensajes de un usuario específico
     */
    public function scopeFromUser($query, $userId)
    {
        return $query->where('sender_id', $userId);
    }

    /**
     * Scope para obtener mensajes de un chat específico
     */
    public function scopeInChat($query, $chatId)
    {
        return $query->where('chat_id', $chatId);
    }
}