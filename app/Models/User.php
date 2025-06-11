<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'password',
        'username',
        'imagen_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Obtiene las estadísticas de juego del usuario.
     */
    public function gameStatistics()
    {
        return $this->hasMany(GameStatistic::class);
    }

    /**
     * Obtiene el historial de partidas del usuario.
     */
    public function gameHistory()
    {
        return $this->hasMany(GameHistory::class);
    }

    /**
     * Obtiene las partidas donde el usuario fue oponente.
     */
    public function opponentHistory()
    {
        return $this->hasMany(GameHistory::class, 'opponent_id');
    }
    /**
     * Amistades donde el usuario es el remitente
     */
    public function sentFriendships()
    {
        return $this->hasMany(Friendship::class, 'user_id');
    }

    /**
     * Amistades donde el usuario es el destinatario
     */
    public function receivedFriendships()
    {
        return $this->hasMany(Friendship::class, 'friend_id');
    }

    /**
     * Relación de amistades enviadas (solo devuelve la relación)
     */
    public function friendships()
    {
        return $this->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    /**
     * Relación de amistades recibidas (solo devuelve la relación)
     */
    public function friendsOf()
    {
        return $this->belongsToMany(User::class, 'friendships', 'friend_id', 'user_id')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    /**
     * Método para obtener todos los amigos (resultado combinado)
     */
    public function getAllFriends()
    {
        // Amigos que el usuario ha añadido
        $friends1 = $this->friendships()
                        ->wherePivot('status', 'accepted')
                        ->get();
        
        // Amigos que han añadido al usuario
        $friends2 = $this->friendsOf()
                        ->wherePivot('status', 'accepted')
                        ->get();
        
        // Combinar y eliminar duplicados
        return $friends1->concat($friends2)->unique('id');
    }

    /**
     * Solicitudes de amistad pendientes enviadas por el usuario
     */
    public function pendingSentRequests()
    {
        return $this->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id')
                    ->wherePivot('status', 'pending');
    }

    /**
     * Solicitudes de amistad pendientes recibidas por el usuario
     */
    public function pendingReceivedRequests()
    {
        return $this->belongsToMany(User::class, 'friendships', 'friend_id', 'user_id')
                    ->wherePivot('status', 'pending');
    }

    /**
     * Usuarios bloqueados por este usuario
     */
    public function blockedUsers()
    {
        return $this->belongsToMany(User::class, 'blocked_users', 'user_id', 'blocked_user_id');
    }

    /**
     * Verifica si el usuario ha bloqueado a otro usuario
     */
    public function hasBlocked($userId)
    {
        return $this->blockedUsers()->where('blocked_user_id', $userId)->exists();
    }

    /**
     * Verifica si el usuario es amigo de otro usuario
     */
    public function isFriendWith($userId)
    {
        // Verifica ambas direcciones de amistad
        return $this->sentFriendships()
                    ->where('friend_id', $userId)
                    ->where('status', 'accepted')
                    ->exists()
                ||
                $this->receivedFriendships()
                    ->where('user_id', $userId)
                    ->where('status', 'accepted')
                    ->exists();
    }

    /**
     * Verifica si hay una solicitud de amistad pendiente con otro usuario
     */
    public function hasPendingFriendRequestWith($userId, $direction = null)
    {
        if ($direction === 'sent' || $direction === null) {
            $sentPending = $this->sentFriendships()
                            ->where('friend_id', $userId)
                            ->where('status', 'pending')
                            ->exists();
            
            if ($sentPending) return true;
        }
        
        if ($direction === 'received' || $direction === null) {
            $receivedPending = $this->receivedFriendships()
                                ->where('user_id', $userId)
                                ->where('status', 'pending')
                                ->exists();
            
            if ($receivedPending) return true;
        }
        
        return false;
    }
}