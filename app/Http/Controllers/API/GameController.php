<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameHistory;
use App\Models\GameStatistic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Controlador de gestión de juegos y estadísticas
 * 
 * Maneja las operaciones relacionadas con juegos disponibles en la plataforma
 * y el registro de resultados de partidas. Incluye sistema de estadísticas
 * diferenciando entre partidas oficiales y locales.
 */
class GameController extends Controller
{
    /**
     * Obtener todos los juegos disponibles
     *
     * Recupera la lista completa de juegos disponibles en la plataforma.
     * Esta información se utiliza para mostrar opciones de juego al usuario
     * y para validar que los resultados se registren para juegos válidos.
     */
    public function getAllGames()
    {
        try {
            // Obtener todos los juegos disponibles en la plataforma
            $games = Game::all();
            
            // Retornar directamente la colección de juegos
            return response()->json($games);

        } catch (\Exception $e) {
            // Log del error para debugging sin exponer detalles internos
            \Log::error('Error al obtener juegos: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener la lista de juegos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Registrar el resultado de una partida
     *
     * Procesa y almacena el resultado de una partida jugada por el usuario.
     * El sistema distingue entre diferentes tipos de partidas:
     * - Online vs Offline: Partidas en línea contra otros usuarios o locales
     * - Contra IA, humanos locales o jugadores en línea
     * - Solo las partidas válidas se contabilizan en estadísticas oficiales
     * 
     * Las estadísticas se actualizan automáticamente para partidas que califican,
     * incluyendo récords personales y contadores de victorias/derrotas.
     */
    public function recordGameResult(Request $request, $gameId)
    {
        try {
            // Verificar que el juego existe en la plataforma
            $game = Game::findOrFail($gameId);
            
            // Validar todos los datos de entrada de la partida
            $validator = Validator::make($request->all(), [
                // Modo de juego: determina si fue una partida en línea o local
                'mode' => 'required|in:online,offline',
                
                // Resultado de la partida desde la perspectiva del usuario
                'result' => 'required|in:won,lost,draw,abandoned',
                
                // Puntuación obtenida (siempre debe ser positiva)
                'score' => 'required|integer|min:0',
                
                // ID del oponente (opcional, solo para partidas contra otros usuarios)
                'opponent_id' => 'nullable|exists:users,id',
                
                // Tipo de oponente para categorizar la partida
                'opponent_type' => 'nullable|in:ai,human,local',
                
                // Sistema de puntos/ranking (opcional)
                'points_earned' => 'nullable|integer|min:0',
                'points_lost' => 'nullable|integer|min:0',
            ]);
            
            // Si hay errores de validación, retornar detalles específicos
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $user = Auth::user();
            
            // Determinar si esta partida debe contabilizarse en estadísticas oficiales
            // Las partidas locales (contra humanos en el mismo dispositivo) no cuentan
            // para evitar inflación artificial de estadísticas
            $countsForStats = true;
            if ($request->input('opponent_type') === 'local') {
                $countsForStats = false;
            }
            
            // Registrar la partida en el historial completo
            // El historial mantiene TODAS las partidas independientemente del tipo
            $gameHistory = new GameHistory();
            $gameHistory->user_id = $user->id;
            $gameHistory->game_id = $game->id;
            $gameHistory->mode = $request->input('mode');
            $gameHistory->opponent_id = $request->input('opponent_id');
            $gameHistory->opponent_type = $request->input('opponent_type', 'ai'); 
            $gameHistory->counts_for_stats = $countsForStats;
            $gameHistory->result = $request->input('result');
            $gameHistory->score = $request->input('score');
            $gameHistory->points_earned = $request->input('points_earned', 0);
            $gameHistory->points_lost = $request->input('points_lost', 0);
            $gameHistory->save();
            
            // Actualizar estadísticas oficiales solo si la partida califica
            if ($countsForStats) {
                // Buscar estadísticas existentes para este juego y modo
                // o crear un nuevo registro si no existe
                $gameStats = GameStatistic::firstOrNew([
                    'user_id' => $user->id,
                    'game_id' => $game->id,
                    'mode' => $request->input('mode')
                ]);
                
                // Inicializar valores para nuevos registros de estadísticas
                if (!$gameStats->exists) {
                    $gameStats->games_played = 0;
                    $gameStats->games_won = 0;
                    $gameStats->games_lost = 0;
                    $gameStats->games_draw = 0;
                    $gameStats->high_score = 0;
                }
                
                // Incrementar contador total de partidas jugadas
                $gameStats->games_played += 1;
                
                // Actualizar contadores específicos según el resultado
                switch ($request->input('result')) {
                    case 'won':
                        $gameStats->games_won += 1;
                        break;
                    case 'lost':
                        $gameStats->games_lost += 1;
                        break;
                    case 'draw':
                        $gameStats->games_draw += 1;
                        break;
                    // Las partidas abandonadas no se contabilizan en estadísticas
                    // para mantener la integridad de los datos de rendimiento
                }
                
                // Actualizar récord personal si se superó la puntuación máxima
                if ($request->input('score') > $gameStats->high_score) {
                    $gameStats->high_score = $request->input('score');
                }
                
                // Guardar las estadísticas actualizadas
                $gameStats->save();
            }
            
            return response()->json([
                'message' => 'Resultado registrado correctamente',
                'game_history' => $gameHistory
            ], 201);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Error específico cuando el juego no existe
            return response()->json([
                'message' => 'Juego no encontrado'
            ], 404);
            
        } catch (\Exception $e) {
            // Error genérico del servidor
            \Log::error('Error al registrar resultado: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al registrar el resultado',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}