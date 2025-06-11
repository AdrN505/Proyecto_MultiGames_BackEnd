<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controlador de historial de partidas
 * 
 * Gestiona la consulta y visualización del historial de partidas jugadas
 * por los usuarios. Proporciona funcionalidades de filtrado, paginación
 * y ordenamiento para facilitar el análisis del rendimiento histórico.
 */
class GameHistoryController extends Controller
{
    /**
     * Obtener el historial completo de partidas del usuario
     *
     * Recupera el historial de partidas del usuario autenticado con opciones
     * avanzadas de filtrado y paginación. Permite filtrar por juego específico,
     * modo de juego y resultado para análisis detallado del rendimiento.
     */
    public function getUserHistory(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Inicializar consulta base para el historial del usuario
            $query = GameHistory::where('user_id', $user->id);
            
            // Aplicar filtro por juego específico si se proporciona
            if ($request->has('game_id')) {
                $query->where('game_id', $request->input('game_id'));
            }
            
            // Aplicar filtro por modo de juego (online/offline)
            if ($request->has('mode')) {
                $query->where('mode', $request->input('mode'));
            }
            
            // Aplicar filtro por resultado de la partida
            if ($request->has('result')) {
                $query->where('result', $request->input('result'));
            }
            
            // Ordenar por fecha de creación (partidas más recientes primero)
            // Esto proporciona una vista cronológica del progreso del usuario
            $query->orderBy('created_at', 'desc');
            
            // Manejar paginación si se solicita explícitamente
            if ($request->has('page')) {
                // Obtener límite de resultados por página (default: 10)
                $limit = $request->input('limit', 10);
                
                // Ejecutar consulta con paginación y cargar datos del juego
                $history = $query->with('game')->paginate($limit);
                
                // Retornar con metadatos completos de paginación
                return response()->json([
                    'data' => $history->items(),
                    'meta' => [
                        'current_page' => $history->currentPage(),
                        'last_page' => $history->lastPage(),
                        'per_page' => $history->perPage(),
                        'total' => $history->total()
                    ]
                ]);
            } else {
                // Sin paginación explícita, aplicar límite simple para rendimiento
                $limit = $request->input('limit', 10);
                $history = $query->with('game')->limit($limit)->get();
                
                // Retornar datos directamente sin metadatos de paginación
                return response()->json($history);
            }

        } catch (\Exception $e) {
            // Log del error para debugging sin exponer detalles sensibles
            \Log::error('Error al obtener historial: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener el historial de partidas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener historial de partidas para un juego específico
     *
     * Recupera el historial de partidas del usuario para un juego en particular.
     * Esta función es útil para análisis detallado del rendimiento en un juego
     * específico, permitiendo ver la evolución y patrones de juego.
     * 
     */
    public function getGameHistory(Request $request, $gameId)
    {
        try {
            // Verificar que el juego existe antes de consultar el historial
            // Esto previene consultas innecesarias y proporciona error específico
            $game = Game::findOrFail($gameId);
            
            $user = Auth::user();
            
            // Inicializar consulta filtrada por usuario y juego específico
            $query = GameHistory::where('user_id', $user->id)
                ->where('game_id', $gameId);
            
            // Aplicar filtro adicional por modo de juego si se proporciona
            if ($request->has('mode')) {
                $query->where('mode', $request->input('mode'));
            }
            
            // Aplicar filtro adicional por resultado si se proporciona
            if ($request->has('result')) {
                $query->where('result', $request->input('result'));
            }
            
            // Ordenar cronológicamente (más reciente primero)
            $query->orderBy('created_at', 'desc');
            
            // Manejar paginación si se solicita
            if ($request->has('page')) {
                $limit = $request->input('limit', 10);
                $history = $query->paginate($limit);
                
                // Incluir datos del juego en la respuesta paginada
                return response()->json([
                    'game' => $game,  // Información del juego consultado
                    'data' => $history->items(),
                    'meta' => [
                        'current_page' => $history->currentPage(),
                        'last_page' => $history->lastPage(),
                        'per_page' => $history->perPage(),
                        'total' => $history->total()
                    ]
                ]);
            } else {
                // Sin paginación, aplicar límite simple
                $limit = $request->input('limit', 10);
                $history = $query->limit($limit)->get();
                
                // Retornar con información del juego incluida
                return response()->json([
                    'game' => $game,
                    'data' => $history
                ]);
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Error específico cuando el juego solicitado no existe
            return response()->json([
                'message' => 'Juego no encontrado'
            ], 404);
            
        } catch (\Exception $e) {
            // Error genérico del servidor
            \Log::error('Error al obtener historial del juego: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener el historial de partidas del juego',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}