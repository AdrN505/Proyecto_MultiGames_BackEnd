<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Controlador de gestión de usuarios
 * 
 * Maneja las operaciones relacionadas con la gestión de perfiles de usuario:
 * - Consulta de datos de perfil
 * - Actualización de imagen de perfil
 * - Eliminación completa de cuenta
 * - Listado de usuarios (para funcionalidades administrativas)
 */
class UserController extends Controller
{
    /**
     * Obtener lista de todos los usuarios
     * 
     * Función auxiliar que retorna todos los usuarios del sistema.
     * Principalmente utilizada para funcionalidades administrativas
     * o para búsquedas de usuarios.
     */
    public function getUsers() 
    {
        return response()->json(User::all());
    }

    /**
     * Obtener datos del perfil del usuario autenticado
     * 
     * Recupera la información completa del perfil del usuario
     * que está actualmente autenticado en el sistema.
     * Útil para mostrar datos personales y configuraciones.
     */
    public function getProfile(Request $request)
    {
        try {
            // Obtener usuario autenticado desde el request
            $user = $request->user();
            
            return response()->json([
                'message' => 'Perfil obtenido correctamente',
                'user' => $user
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al obtener perfil: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener datos del perfil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar la imagen de perfil del usuario
     * 
     * Procesa la subida y actualización de la imagen de perfil.
     * Incluye validación del archivo, eliminación de imagen anterior
     * y almacenamiento seguro de la nueva imagen.
     * 
     * La imagen anterior se elimina automáticamente para evitar
     * acumulación de archivos no utilizados.
     */
    public function updateProfileImage(Request $request)
    {
        try {
            // Validar la imagen subida con restricciones de seguridad
            $validator = Validator::make($request->all(), [
                'imagen' => 'required|image|max:2048', // Máximo 2MB para optimizar rendimiento
            ], [
                'imagen.required' => 'La imagen es obligatoria.',
                'imagen.image' => 'El archivo debe ser una imagen.',
                'imagen.max' => 'La imagen no debe superar los 2MB.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // Eliminar imagen anterior si existe para liberar espacio
            if ($user->imagen_url) {
                // Extraer el path relativo desde la URL completa
                $oldPath = str_replace(url('/storage/'), '', $user->imagen_url);
                
                // Verificar que el archivo existe antes de intentar eliminarlo
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // Procesar y guardar la nueva imagen
            if ($request->hasFile('imagen') && $request->file('imagen')->isValid()) {
                // Almacenar en el directorio 'avatars' del disco público
                $path = $request->file('imagen')->store('avatars', 'public');
                
                // Generar URL completa accesible desde el frontend
                $user->imagen_url = url(Storage::url($path));
                $user->save();
            }

            return response()->json([
                'message' => 'Imagen de perfil actualizada correctamente',
                'user' => $user
            ]);

        } catch (\Exception $e) {
            // Log detallado del error para debugging
            \Log::error('Error al actualizar imagen: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al actualizar la imagen de perfil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar cuenta de usuario completa
     * 
     * Proceso de eliminación completa y permanente de una cuenta de usuario.
     * Esta operación es IRREVERSIBLE y elimina:
     * - Imagen de perfil
     * - Tokens de autenticación (cierra todas las sesiones)
     * - Estadísticas de juegos
     * - Historial de partidas
     * - Relaciones de amistad
     * - El registro del usuario
     */
    public function deleteAccount(Request $request)
    {
        try {
            $user = $request->user();
            
            // Log de auditoría para seguimiento de eliminaciones
            \Log::info('Iniciando eliminación de cuenta para usuario ID: ' . $user->id);
            
            // Paso 1: Eliminar imagen de perfil del almacenamiento
            if ($user->imagen_url) {
                // Extraer path relativo desde la URL
                $imagePath = str_replace(url('/storage/'), '', $user->imagen_url);
                
                if (Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                    \Log::info('Imagen eliminada: ' . $imagePath);
                }
            }
            
            // Paso 2: Eliminar todos los tokens (cerrar todas las sesiones)
            $deletedTokens = $user->tokens()->count();
            $user->tokens()->delete();
            \Log::info("Eliminados {$deletedTokens} tokens para usuario ID: {$user->id}");
            
            // Paso 3: Eliminar estadísticas de juegos
            // Aunque las FK tienen cascade, es más explícito y controlado
            $deletedStats = \DB::table('game_statistics')->where('user_id', $user->id)->count();
            \DB::table('game_statistics')->where('user_id', $user->id)->delete();
            \Log::info("Eliminadas {$deletedStats} estadísticas de juego para usuario ID: {$user->id}");
            
            // Paso 4: Eliminar historial de juegos
            $deletedHistory = \DB::table('game_history')->where('user_id', $user->id)->count();
            \DB::table('game_history')->where('user_id', $user->id)->delete();
            \Log::info("Eliminadas {$deletedHistory} entradas de historial para usuario ID: {$user->id}");
            
            // Paso 5: Eliminar relaciones de amistad si la tabla existe
            if (\Schema::hasTable('relationships')) {
                $deletedRelationships = \DB::table('relationships')
                    ->where('user_id', $user->id)
                    ->orWhere('friend_id', $user->id)
                    ->count();
                
                \DB::table('relationships')
                    ->where('user_id', $user->id)
                    ->orWhere('friend_id', $user->id)
                    ->delete();
                
                \Log::info("Eliminadas {$deletedRelationships} relaciones de amistad para usuario ID: {$user->id}");
            }
            
            // Paso 6: Eliminar el usuario principal
            // Guardar datos para el log antes de la eliminación
            $userId = $user->id;
            $userEmail = $user->email;
            $user->delete();
            
            \Log::info("Usuario eliminado exitosamente - ID: {$userId}, Email: {$userEmail}");
            
            return response()->json([
                'message' => 'Cuenta eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            // Log detallado del error para debugging
            \Log::error('Error al eliminar cuenta: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'message' => 'Error al eliminar la cuenta',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}