<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BlockedUser;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controlador de gestión de relaciones entre usuarios
 * 
 * Maneja todas las operaciones relacionadas con las interacciones sociales:
 * - Gestión de amistades (enviar, aceptar, rechazar, eliminar)
 * - Sistema de bloqueo de usuarios
 * - Consulta de listas de amigos y usuarios bloqueados
 * - Validación de permisos y estados de relación
 */
class RelationshipController extends Controller
{
    /**
     * Obtener la lista de amigos del usuario
     * 
     * Recupera todos los usuarios que tienen una relación de amistad
     * aceptada con el usuario autenticado. Utiliza el método getAllFriends()
     * del modelo User para una consulta optimizada.
     */
    public function getFriends()
    {
        try {
            $user = Auth::user();
            
            // Usar el método getAllFriends() del modelo User
            // Este método maneja la lógica de consulta bidireccional de amistades
            $friends = $user->getAllFriends();
            
            return response()->json($friends);

        } catch (\Exception $e) {
            // Log detallado del error incluyendo información del usuario
            \Log::error('Error al obtener amigos: ' . $e->getMessage(), [
                'userId' => Auth::id(),
                'exception' => $e
            ]);
            
            return response()->json([
                'message' => 'Error al obtener la lista de amigos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener solicitudes de amistad pendientes
     * 
     * Recupera todas las solicitudes de amistad que el usuario ha recibido
     * y que están pendientes de respuesta. Útil para mostrar notificaciones
     * y permitir al usuario gestionar las peticiones entrantes.
     */
    public function getPendingRequests()
    {
        $user = Auth::user();
        
        // Obtener solicitudes pendientes recibidas usando la relación del modelo
        $pendingRequests = $user->pendingReceivedRequests;
        
        return response()->json($pendingRequests);
    }
    
    /**
     * Enviar solicitud de amistad
     * 
     * Crea una nueva solicitud de amistad entre el usuario autenticado
     * y el usuario especificado. Incluye múltiples validaciones:
     * - Verificar que el usuario objetivo existe
     * - Prevenir auto-solicitudes
     * - Evitar duplicar solicitudes existentes
     * - Respetar bloqueos entre usuarios
     */
    public function sendFriendRequest(Request $request, $userId)
    {
        $user = Auth::user();
        
        // Verificar que no se esté enviando solicitud a sí mismo
        if ($user->id == $userId) {
            return response()->json([
                'message' => 'No puedes enviarte una solicitud de amistad a ti mismo',
            ], 400);
        }
        
        // Verificar que el usuario objetivo exista en la base de datos
        $friend = User::find($userId);
        if (!$friend) {
            return response()->json([
                'message' => 'Usuario no encontrado',
            ], 404);
        }
        
        // Verificar que no sea ya un amigo
        // Esto previene solicitudes redundantes
        if ($user->isFriendWith($userId)) {
            return response()->json([
                'message' => 'Ya eres amigo de este usuario',
            ], 400);
        }
        
        // Verificar que no haya solicitud pendiente en cualquier dirección
        if ($user->hasPendingFriendRequestWith($userId)) {
            return response()->json([
                'message' => 'Ya existe una solicitud de amistad pendiente',
            ], 400);
        }
        
        // Verificar bloqueos bidireccionales
        // Si cualquiera de los dos ha bloqueado al otro, no permitir solicitud
        if ($user->hasBlocked($userId) || $friend->hasBlocked($user->id)) {
            return response()->json([
                'message' => 'No se puede enviar solicitud de amistad',
            ], 400);
        }
        
        // Crear la nueva solicitud de amistad
        $friendship = new Friendship();
        $friendship->user_id = $user->id;        // Quien envía la solicitud
        $friendship->friend_id = $userId;        // Quien recibe la solicitud
        $friendship->status = 'pending';         // Estado inicial
        $friendship->save();
        
        return response()->json([
            'message' => 'Solicitud de amistad enviada correctamente',
            'friendship' => $friendship
        ], 201);
    }
    
    /**
     * Aceptar solicitud de amistad
     * 
     * Procesa la aceptación de una solicitud de amistad pendiente.
     * Cambia el estado de 'pending' a 'accepted', estableciendo
     * oficialmente la relación de amistad entre ambos usuarios.
     */
    public function acceptFriendRequest(Request $request, $userId)
    {
        $user = Auth::user();
        
        // Buscar la solicitud pendiente específica
        // La solicitud debe haber sido enviada por $userId al usuario actual
        $friendship = Friendship::where('user_id', $userId)
                              ->where('friend_id', $user->id)
                              ->where('status', 'pending')
                              ->first();
        
        if (!$friendship) {
            return response()->json([
                'message' => 'No se encontró una solicitud de amistad pendiente',
            ], 404);
        }
        
        // Actualizar estado a aceptado
        // Esto establece la amistad oficial entre ambos usuarios
        $friendship->status = 'accepted';
        $friendship->save();
        
        return response()->json([
            'message' => 'Solicitud de amistad aceptada correctamente',
            'friendship' => $friendship
        ]);
    }
    
    /**
     * Rechazar solicitud de amistad
     * 
     * Procesa el rechazo de una solicitud de amistad eliminándola
     * completamente del sistema. El usuario que envió la solicitud
     * podrá enviar una nueva en el futuro si lo desea.
     */
    public function rejectFriendRequest(Request $request, $userId)
    {
        $user = Auth::user();
        
        // Buscar la solicitud pendiente
        $friendship = Friendship::where('user_id', $userId)
                              ->where('friend_id', $user->id)
                              ->where('status', 'pending')
                              ->first();
        
        if (!$friendship) {
            return response()->json([
                'message' => 'No se encontró una solicitud de amistad pendiente',
            ], 404);
        }
        
        // Eliminar la solicitud completamente
        // Esto permite que se pueda enviar una nueva solicitud en el futuro
        $friendship->delete();
        
        return response()->json([
            'message' => 'Solicitud de amistad rechazada correctamente',
        ]);
    }
    
    /**
     * Eliminar amistad existente
     * 
     * Termina una relación de amistad existente eliminando el registro
     * correspondiente. La búsqueda es bidireccional ya que la amistad
     * puede estar registrada en cualquier dirección.
     */
    public function removeFriend(Request $request, $userId)
    {
        $user = Auth::user();
        
        // Eliminar la amistad considerando ambas direcciones posibles
        // La amistad puede estar registrada como user_id -> friend_id o viceversa
        $deleted = Friendship::where(function($query) use ($user, $userId) {
                           $query->where('user_id', $user->id)
                                 ->where('friend_id', $userId);
                       })
                       ->orWhere(function($query) use ($user, $userId) {
                           $query->where('user_id', $userId)
                                 ->where('friend_id', $user->id);
                       })
                       ->delete();
        
        if (!$deleted) {
            return response()->json([
                'message' => 'No se encontró una amistad para eliminar',
            ], 404);
        }
        
        return response()->json([
            'message' => 'Amistad eliminada correctamente',
        ]);
    }
    
    /**
     * Bloquear usuario
     * 
     * Establece un bloqueo unidireccional hacia otro usuario.
     * El bloqueo previene:
     * - Envío de solicitudes de amistad
     * - Inicio de conversaciones
     * - Visualización en búsquedas
     * 
     * También elimina cualquier relación de amistad existente.
     */
    public function blockUser(Request $request, $userId)
    {
        $user = Auth::user();
        
        // Verificar que no se esté bloqueando a sí mismo
        if ($user->id == $userId) {
            return response()->json([
                'message' => 'No puedes bloquearte a ti mismo',
            ], 400);
        }
        
        // Verificar que el usuario objetivo exista
        $blockedUser = User::find($userId);
        if (!$blockedUser) {
            return response()->json([
                'message' => 'Usuario no encontrado',
            ], 404);
        }
        
        // Verificar que no esté ya bloqueado
        if ($user->hasBlocked($userId)) {
            return response()->json([
                'message' => 'El usuario ya está bloqueado',
            ], 400);
        }
        
        // Eliminar cualquier amistad o solicitud pendiente existente
        // El bloqueo debe limpiar todas las relaciones previas
        Friendship::where(function($query) use ($user, $userId) {
                    $query->where('user_id', $user->id)
                          ->where('friend_id', $userId);
                })
                ->orWhere(function($query) use ($user, $userId) {
                    $query->where('user_id', $userId)
                          ->where('friend_id', $user->id);
                })
                ->delete();
        
        // Crear el registro de bloqueo
        $blockedUserRecord = new BlockedUser();
        $blockedUserRecord->user_id = $user->id;
        $blockedUserRecord->blocked_user_id = $userId;
        $blockedUserRecord->save();
        
        return response()->json([
            'message' => 'Usuario bloqueado correctamente',
        ], 201);
    }
    
    /**
     * Desbloquear usuario
     * 
     * Elimina un bloqueo existente, permitiendo nuevamente la interacción
     * entre usuarios. Esto no restaura automáticamente ninguna amistad
     * previa que haya sido eliminada por el bloqueo.
     */
    public function unblockUser(Request $request, $userId)
    {
        $user = Auth::user();
        
        // Eliminar el registro de bloqueo específico
        $deleted = BlockedUser::where('user_id', $user->id)
                           ->where('blocked_user_id', $userId)
                           ->delete();
        
        if (!$deleted) {
            return response()->json([
                'message' => 'No se encontró un bloqueo para este usuario',
            ], 404);
        }
        
        return response()->json([
            'message' => 'Usuario desbloqueado correctamente',
        ]);
    }
    
    /**
     * Obtener lista de usuarios bloqueados
     * 
     * Recupera todos los usuarios que el usuario autenticado ha bloqueado.
     * Útil para mostrar la lista de usuarios bloqueados y permitir
     * gestionar los bloqueos existentes.
     */
    public function getBlockedUsers()
    {
        $user = Auth::user();
        
        // Obtener usuarios bloqueados usando la relación del modelo
        $blockedUsers = $user->blockedUsers;
        
        return response()->json($blockedUsers);
    }
}