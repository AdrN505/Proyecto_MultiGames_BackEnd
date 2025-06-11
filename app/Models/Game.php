<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    use HasFactory;

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'mode',
        'description',
        'icon_path',
        'is_multiplayer',
    ];

    /**
     * Los atributos que deben convertirse a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'is_multiplayer' => 'boolean',
    ];

    /**
     * Obtiene las estadísticas asociadas con el juego.
     */
    public function statistics()
    {
        return $this->hasMany(GameStatistic::class);
    }

    /**
     * Obtiene el historial de partidas asociadas con el juego.
     */
    public function history()
    {
        return $this->hasMany(GameHistory::class);
    }
}
