<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameHistory extends Model
{
    use HasFactory;

    protected $table = 'game_history';

    protected $fillable = [
        'user_id',
        'game_id',
        'mode',
        'opponent_id',
        'opponent_type',
        'counts_for_stats',
        'result',
        'score',
        'points_earned',
        'points_lost',
    ];

    protected $casts = [
        'counts_for_stats' => 'boolean',
        'score' => 'integer',
        'points_earned' => 'integer',
        'points_lost' => 'integer',
    ];

    /**
     * Obtiene el usuario al que pertenece este historial.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtiene el juego al que pertenece este historial.
     */
    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Obtiene el oponente asociado con esta partida (si existe).
     */
    public function opponent()
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }
}