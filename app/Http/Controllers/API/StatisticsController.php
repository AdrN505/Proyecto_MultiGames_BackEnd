<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameStatistic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controlador de estadísticas de juegos
 * 
 * Maneja la consulta y visualización de estadísticas de rendimiento
 * de los usuarios en diferentes juegos y modos. Proporciona endpoints
 * para análisis detallado del progreso y desempeño del jugador.
 */
class StatisticsController extends Controller
{
    /**
     * Obtener todas las estadísticas del usuario
     * 
     * Recupera el conjunto completo de estadísticas de juego para
     * el usuario autenticado, incluyendo ambos modos (online y offline)
     * y todos los juegos en los que ha participado.
     * 
     * Incluye información del juego relacionado para facilitar
     * la presentación en el frontend.
     */
    public function getAllStatistics()
    {
        try {
            $user = Auth::user();
            
            // Obtener estadísticas con relaciones cargadas para optimizar consultas
            // La relación 'game' proporciona información del juego (nombre, descripción, etc.)
            $statistics = GameStatistic::where('user_id', $user->id)
                ->with('game')  
                ->get();
            
            return response()->json($statistics);

        } catch (\Exception $e) {
            \Log::error('Error al obtener estadísticas: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener estadísticas de modo offline
     * 
     * Recupera únicamente las estadísticas de partidas jugadas en modo offline.
     * Este modo incluye partidas contra IA y partidas locales multijugador
     * en el mismo dispositivo.
     * 
     * Útil para analizar el rendimiento del usuario en sesiones de práctica
     * y juego individual.
     */
    public function getOfflineStatistics()
    {
        try {
            $user = Auth::user();
            
            // Filtrar por modo offline específicamente
            $statistics = GameStatistic::where('user_id', $user->id)
                ->where('mode', 'offline')
                ->with('game')
                ->get();
            
            return response()->json($statistics);

        } catch (\Exception $e) {
            \Log::error('Error al obtener estadísticas offline: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener estadísticas offline',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener estadísticas de modo online
     * 
     * Recupera únicamente las estadísticas de partidas jugadas en modo online.
     * Este modo incluye partidas competitivas contra otros usuarios reales
     * conectados a través de la plataforma.
     * 
     * Estas estadísticas son generalmente más relevantes para rankings
     * y comparaciones de habilidad entre jugadores.
     */
    public function getOnlineStatistics()
    {
        try {
            $user = Auth::user();
            
            // Filtrar por modo online específicamente
            $statistics = GameStatistic::where('user_id', $user->id)
                ->where('mode', 'online')
                ->with('game')
                ->get();
            
            return response()->json($statistics);

        } catch (\Exception $e) {
            \Log::error('Error al obtener estadísticas online: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener estadísticas online',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener estadísticas de un juego específico
     * 
     * Recupera todas las estadísticas (ambos modos) para un juego en particular.
     * Útil para análisis detallado del rendimiento del usuario en un juego
     * específico, permitiendo comparar su desempeño entre modos online y offline.
     * 
     * Incluye validación de existencia del juego para prevenir consultas
     * sobre juegos inexistentes.
     */
    public function getGameStatistics($gameId)
    {
        try {
            // Verificar que el juego existe antes de consultar estadísticas
            $game = Game::findOrFail($gameId);
            
            $user = Auth::user();
            
            // Obtener todas las estadísticas para este juego específico
            // Puede incluir múltiples registros si el usuario ha jugado
            // en ambos modos (online y offline)
            $statistics = GameStatistic::where('user_id', $user->id)
                ->where('game_id', $gameId)
                ->get();
            
            // Retornar tanto la información del juego como las estadísticas
            return response()->json([
                'game' => $game,
                'statistics' => $statistics
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Error específico cuando el juego no existe
            return response()->json([
                'message' => 'Juego no encontrado'
            ], 404);
            
        } catch (\Exception $e) {
            \Log::error('Error al obtener estadísticas del juego: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener estadísticas del juego',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}