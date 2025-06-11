<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameStatistic extends Model
{
    use HasFactory;

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'game_id',
        'mode',
        'games_played',
        'games_won',
        'games_lost',
        'games_draw',
        'high_score',
    ];

    /**
     * Los atributos que deben convertirse a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'games_played' => 'integer',
        'games_won' => 'integer',
        'games_lost' => 'integer',
        'games_draw' => 'integer',
        'high_score' => 'integer',
    ];

    /**
     * Obtiene el usuario al que pertenecen estas estadísticas.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtiene el juego al que pertenecen estas estadísticas.
     */
    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Obtiene o crea estadísticas para un usuario, juego y modo específicos
     * 
     * @param int $userId ID del usuario
     * @param int $gameId ID del juego
     * @param string $mode Modo de juego (online/offline)
     * @return GameStatistic Instancia de estadísticas
     */
    public static function getOrCreate($userId, $gameId, $mode = 'offline')
    {
        $stats = self::firstOrNew([
            'user_id' => $userId,
            'game_id' => $gameId,
            'mode' => $mode
        ]);

        // Inicializar valores si es una nueva instancia
        if (!$stats->exists) {
            $stats->games_played = 0;
            $stats->games_won = 0;
            $stats->games_lost = 0;
            $stats->games_draw = 0;
            $stats->high_score = 0;
            $stats->save();
        }

        return $stats;
    }

    /**
     * Actualiza las estadísticas después de una partida
     * 
     * @param string $result Resultado de la partida ('won', 'lost', 'draw')
     * @param int $score Puntuación de la partida
     * @return GameStatistic Instancia actualizada
     */
    public function updateAfterGame($result, $score)
    {
        // Incrementar contador de partidas jugadas
        $this->games_played += 1;

        // Actualizar según resultado
        switch ($result) {
            case 'won':
                $this->games_won += 1;
                break;
            case 'lost':
                $this->games_lost += 1;
                break;
            case 'draw':
                $this->games_draw += 1;
                break;
        }

        // Actualizar puntuación máxima si es necesario
        if ($score > $this->high_score) {
            $this->high_score = $score;
        }

        // Guardar cambios
        $this->save();

        return $this;
    }
}