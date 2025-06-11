<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;

/**
 * Controlador de sistema de chat en tiempo real
 * 
 * Maneja todas las operaciones relacionadas con el sistema de mensajería:
 * - Obtención y gestión de conversaciones
 * - Envío y recepción de mensajes
 * - Control de estado de lectura
 * - Verificación de permisos entre usuarios
 */
class ChatController extends Controller
{
    /**
     * Obtener todos los chats del usuario autenticado
     * 
     * Recupera todas las conversaciones en las que participa el usuario actual,
     * incluyendo información del otro participante, último mensaje, conteo de
     * mensajes no leídos y datos de actualización para ordenamiento.
     * 
     * Implementación optimizada que evita problemas de relaciones complejas
     * obteniendo el último mensaje manualmente para mayor control.
     */
    public function getChats(Request $request)
    {
        try {
            // Obtener usuario autenticado
            $user = Auth::user();
            
            // Verificación de seguridad: usuario debe estar autenticado
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Obtener chats donde el usuario participa como user1 o user2
            // Se incluyen las relaciones de ambos usuarios para obtener sus datos
            $chats = Chat::where('user1_id', $user->id)
                ->orWhere('user2_id', $user->id)
                ->with(['user1', 'user2'])  // Cargar datos de ambos participantes
                ->orderBy('updated_at', 'desc') // Ordenar por actividad más reciente
                ->get();

            // Formatear datos para optimizar el consumo del frontend
            $formattedChats = $chats->map(function ($chat) use ($user) {
                // Determinar quién es el "otro usuario" en la conversación
                $otherUser = $chat->user1_id === $user->id ? $chat->user2 : $chat->user1;
                
                // Obtener último mensaje de la conversación manualmente
                // Esto evita problemas de relaciones complejas y da más control
                $latestMessage = ChatMessage::where('chat_id', $chat->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                // Contar mensajes no leídos del otro usuario
                // Solo cuenta mensajes enviados por otros y que el usuario actual no ha leído
                $unreadCount = ChatMessage::where('chat_id', $chat->id)
                    ->where('sender_id', '!=', $user->id)  // Excluir mensajes propios
                    ->where('is_read', false)              // Solo no leídos
                    ->count();

                // Formatear datos para el frontend con estructura consistente
                return [
                    'id' => $chat->id,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'username' => $otherUser->username,
                        'name' => $otherUser->name ?? $otherUser->username, // Fallback al username
                        'imagen_url' => $otherUser->imagen_url,
                    ],
                    'latest_message' => $latestMessage ? [
                        'message' => $latestMessage->message,
                        'created_at' => $latestMessage->created_at,
                        'sender_id' => $latestMessage->sender_id
                    ] : null, // null si no hay mensajes aún
                    'unread_count' => $unreadCount,
                    'updated_at' => $chat->updated_at
                ];
            });

            return response()->json([
                'success' => true,
                'chats' => $formattedChats
            ]);

        } catch (\Exception $e) {
            // Log del error para debugging sin exponer detalles sensibles
            \Log::error('Error al obtener chats: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los chats'
            ], 500);
        }
    }

    /**
     * Iniciar un nuevo chat o obtener uno existente
     * 
     * Crea una nueva conversación entre dos usuarios o recupera una existente.
     * Incluye verificaciones de seguridad para prevenir chats con uno mismo
     * y validación opcional de amistad entre usuarios.
     * 
     * El sistema está diseñado para ser flexible, permitiendo temporalmente
     * chats sin verificación de amistad para facilitar el debugging.
     */
    public function startChat(Request $request)
    {
        try {
            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'other_user_id' => 'required|integer|exists:users,id' // Usuario debe existir
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $otherUserId = $request->other_user_id;

            // Verificación de seguridad: no permitir chat consigo mismo
            if ($user->id === $otherUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes chatear contigo mismo'
                ], 400);
            }

            // Verificación de amistad entre usuarios
            $areFriends = DB::table('friendships')
                ->where(function ($query) use ($user, $otherUserId) {
                    $query->where('user_id', $user->id)
                            ->where('friend_id', $otherUserId);
                })
                ->orWhere(function ($query) use ($user, $otherUserId) {
                    $query->where('user_id', $otherUserId)
                            ->where('friend_id', $user->id);
                })
                ->where('status', 'accepted')
                ->exists();

            // Buscar chat existente entre los dos usuarios
            // La búsqueda considera ambas posibles configuraciones de user1_id y user2_id
            $chat = Chat::where(function ($query) use ($user, $otherUserId) {
                $query->where('user1_id', $user->id)
                      ->where('user2_id', $otherUserId);
            })->orWhere(function ($query) use ($user, $otherUserId) {
                $query->where('user1_id', $otherUserId)
                      ->where('user2_id', $user->id);
            })->first();

            // Si no existe chat, crear uno nuevo
            if (!$chat) {
                // Crear chat con IDs ordenados para mantener consistencia
                // El ID menor siempre será user1_id para evitar duplicados
                $chat = Chat::create([
                    'user1_id' => min($user->id, $otherUserId),
                    'user2_id' => max($user->id, $otherUserId)
                ]);
            }

            // Obtener información completa del otro usuario
            $otherUser = User::findOrFail($otherUserId);

            return response()->json([
                'success' => true,
                'chat' => [
                    'id' => $chat->id,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'username' => $otherUser->username,
                        'name' => $otherUser->name ?? $otherUser->username,
                        'imagen_url' => $otherUser->imagen_url,
                    ],
                    'created_at' => $chat->created_at
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al iniciar chat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar el chat'
            ], 500);
        }
    }

    /**
     * Obtener mensajes de un chat específico
     * 
     * Recupera el historial de mensajes de una conversación con paginación.
     * Incluye verificación de permisos para asegurar que solo los participantes
     * del chat puedan acceder a los mensajes.
     * 
     * Los mensajes se devuelven en orden cronológico inverso para facilitar
     * la carga incremental en el frontend.
     */
    public function getChatMessages(Request $request, $chatId)
    {
        try {
            $user = Auth::user();
            
            // Parámetros de paginación con valores por defecto
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 50); // 50 mensajes por página por defecto

            // Verificar que el usuario tiene permisos para acceder a este chat
            $chat = Chat::where('id', $chatId)
                ->where(function ($query) use ($user) {
                    // El usuario debe ser participante del chat
                    $query->where('user1_id', $user->id)
                          ->orWhere('user2_id', $user->id);
                })
                ->first();

            // Si no se encuentra el chat o no tiene permisos
            if (!$chat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat no encontrado o sin acceso'
                ], 404);
            }

            // Obtener mensajes con paginación y datos del remitente
            $messages = ChatMessage::where('chat_id', $chatId)
                ->with('sender') // Cargar datos del usuario que envió cada mensaje
                ->orderBy('created_at', 'desc') // Más recientes primero para paginación
                ->paginate($perPage, ['*'], 'page', $page);

            // Formatear mensajes para el frontend
            $formattedMessages = $messages->getCollection()->map(function ($message) {
                return [
                    'id' => $message->id,
                    'message' => $message->message,
                    'sender' => [
                        'id' => $message->sender->id,
                        'username' => $message->sender->username,
                        'name' => $message->sender->name ?? $message->sender->username
                    ],
                    'is_read' => $message->is_read,
                    'created_at' => $message->created_at,
                    'updated_at' => $message->updated_at
                ];
            })->reverse()->values(); // Invertir orden para mostrar cronológicamente

            return response()->json([
                'success' => true,
                'messages' => $formattedMessages,
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                    'has_more_pages' => $messages->hasMorePages()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al obtener mensajes: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los mensajes'
            ], 500);
        }
    }

    /**
     * Enviar un nuevo mensaje
     * 
     * Procesa el envío de un mensaje en una conversación existente.
     * Incluye validación del contenido, verificación de permisos y
     * actualización automática del timestamp del chat para ordenamiento.
     */
    public function sendMessage(Request $request)
    {
        try {
            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'chat_id' => 'required|integer|exists:chats,id', // Chat debe existir
                'message' => 'required|string|max:1000'          // Límite de caracteres
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $chatId = $request->chat_id;
            $messageText = trim($request->message); // Limpiar espacios

            // Verificar que el mensaje no esté vacío después del trim
            if (empty($messageText)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El mensaje no puede estar vacío'
                ], 400);
            }

            // Verificar permisos de acceso al chat
            $chat = Chat::where('id', $chatId)
                ->where(function ($query) use ($user) {
                    $query->where('user1_id', $user->id)
                          ->orWhere('user2_id', $user->id);
                })
                ->first();

            if (!$chat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat no encontrado o sin acceso'
                ], 404);
            }

            // Crear el nuevo mensaje
            $message = ChatMessage::create([
                'chat_id' => $chatId,
                'sender_id' => $user->id,
                'message' => $messageText,
                'is_read' => false // Inicialmente no leído por el destinatario
            ]);

            // Actualizar timestamp del chat para ordenamiento por actividad
            $chat->touch();

            // Cargar relación del remitente para la respuesta
            $message->load('sender');

            // Formatear respuesta para el frontend
            $formattedMessage = [
                'id' => $message->id,
                'message' => $message->message,
                'sender' => [
                    'id' => $message->sender->id,
                    'username' => $message->sender->username,
                    'name' => $message->sender->name ?? $message->sender->username
                ],
                'is_read' => $message->is_read,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at
            ];

            return response()->json([
                'success' => true,
                'message' => $formattedMessage
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error al enviar mensaje: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el mensaje'
            ], 500);
        }
    }

    /**
     * Marcar mensajes como leídos
     * 
     * Actualiza el estado de lectura de todos los mensajes no leídos
     * en una conversación específica. Solo afecta mensajes enviados
     * por otros usuarios, no los propios.
     */
    public function markAsRead(Request $request, $chatId)
    {
        try {
            $user = Auth::user();

            // Verificar permisos de acceso al chat
            $chat = Chat::where('id', $chatId)
                ->where(function ($query) use ($user) {
                    $query->where('user1_id', $user->id)
                          ->orWhere('user2_id', $user->id);
                })
                ->first();

            if (!$chat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat no encontrado o sin acceso'
                ], 404);
            }

            // Marcar como leídos solo los mensajes de otros usuarios
            // No se marcan los propios mensajes para evitar confusiones
            $updatedCount = ChatMessage::where('chat_id', $chatId)
                ->where('sender_id', '!=', $user->id) // Excluir mensajes propios
                ->where('is_read', false)             // Solo los no leídos
                ->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'updated_count' => $updatedCount,
                'message' => 'Mensajes marcados como leídos'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al marcar mensajes como leídos: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar los mensajes como leídos'
            ], 500);
        }
    }

    /**
     * Obtener contador de mensajes no leídos
     * 
     * Calcula el total de mensajes no leídos para el usuario actual
     * en todas sus conversaciones. Útil para mostrar badges de notificación
     * en la interfaz de usuario.
     */
    public function getUnreadCount(Request $request)
    {
        try {
            $user = Auth::user();

            // Obtener IDs de todos los chats donde participa el usuario
            $chatIds = Chat::where('user1_id', $user->id)
                ->orWhere('user2_id', $user->id)
                ->pluck('id');

            // Contar mensajes no leídos en todos los chats
            $unreadCount = ChatMessage::whereIn('chat_id', $chatIds)
                ->where('sender_id', '!=', $user->id) // Excluir mensajes propios
                ->where('is_read', false)             // Solo no leídos
                ->count();

            return response()->json([
                'success' => true,
                'unread_count' => $unreadCount
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al obtener contador de no leídos: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el contador de mensajes no leídos'
            ], 500);
        }
    }

    /**
     * Eliminar un chat completo
     * 
     * Elimina permanentemente una conversación y todos sus mensajes.
     * Esta operación es irreversible y requiere que el usuario sea
     * participante del chat.
     */
    public function deleteChat(Request $request, $chatId)
    {
        try {
            $user = Auth::user();

            // Verificar permisos de acceso al chat
            $chat = Chat::where('id', $chatId)
                ->where(function ($query) use ($user) {
                    $query->where('user1_id', $user->id)
                          ->orWhere('user2_id', $user->id);
                })
                ->first();

            if (!$chat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat no encontrado o sin acceso'
                ], 404);
            }

            // Eliminar todos los mensajes del chat primero
            // Esto mantiene la integridad referencial
            ChatMessage::where('chat_id', $chatId)->delete();

            // Eliminar el chat
            $chat->delete();

            return response()->json([
                'success' => true,
                'message' => 'Chat eliminado correctamente'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al eliminar chat: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el chat'
            ], 500);
        }
    }
}