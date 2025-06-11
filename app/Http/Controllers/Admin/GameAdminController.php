<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/** 
 * Clase para que los admin puedan gestionar los juegos existentes
 */
class GameAdminController extends Controller
{
    /**
     * Muestra todos los juegos.
     */
    public function index()
    {
        $games = Game::all(); // Obtener todos los registros de juegos
        return response()->json($games); // Devolver respuesta en formato JSON
    }
    
    /**
     * Almacena un nuevo juego en la base de datos.
     */
    public function store(Request $request)
    {
        // Validación de los datos recibidos
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'mode' => 'required|in:online,offline,both', // Solo se permiten estos valores
            'description' => 'nullable|string',
            'icon' => 'nullable|image|max:2048', // Imagen opcional, máximo 2MB
            'is_multiplayer' => 'required|boolean',
        ]);
        
        // Devolver errores si la validación falla
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Crear una nueva instancia del modelo Game
        $game = new Game();
        $game->name = $request->name;
        $game->mode = $request->mode;
        $game->description = $request->description;
        $game->is_multiplayer = $request->is_multiplayer;
        
        // Procesar y guardar la imagen del icono si se proporciona
        if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
            $path = $request->file('icon')->store('icons', 'public'); // Guardar en almacenamiento público
            $game->icon_path = $path;
        }
        
        // Guardar el juego en la base de datos
        $game->save();
        
        // Devolver respuesta de éxito
        return response()->json([
            'message' => 'Juego creado correctamente',
            'game' => $game
        ], 201);
    }
    
    /**
     * Actualiza un juego existente.
     */
    public function update(Request $request, $id)
    {
        $game = Game::findOrFail($id); // Buscar juego por ID o lanzar error 404
        
        // Validar los campos presentes en la solicitud
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'mode' => 'sometimes|required|in:online,offline,both',
            'description' => 'nullable|string',
            'icon' => 'nullable|image|max:2048',
            'is_multiplayer' => 'sometimes|required|boolean',
        ]);
        
        // Retornar errores si la validación falla
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Actualizar campos solo si están presentes en la solicitud
        if ($request->has('name')) {
            $game->name = $request->name;
        }
        
        if ($request->has('mode')) {
            $game->mode = $request->mode;
        }
        
        if ($request->has('description')) {
            $game->description = $request->description;
        }
        
        if ($request->has('is_multiplayer')) {
            $game->is_multiplayer = $request->is_multiplayer;
        }
        
        // Procesar nueva imagen si se ha subido una válida
        if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
            // Eliminar la imagen anterior si existe
            if ($game->icon_path && Storage::disk('public')->exists($game->icon_path)) {
                Storage::disk('public')->delete($game->icon_path);
            }
            
            // Almacenar la nueva imagen y actualizar el campo
            $path = $request->file('icon')->store('icons', 'public');
            $game->icon_path = $path;
        }
        
        // Guardar cambios
        $game->save();
        
        // Devolver respuesta de éxito
        return response()->json([
            'message' => 'Juego actualizado correctamente',
            'game' => $game
        ]);
    }
    
    /**
     * Elimina un juego existente.
     */
    public function destroy($id)
    {
        $game = Game::findOrFail($id); // Buscar juego o lanzar error 404
        
        // Eliminar icono del almacenamiento si existe
        if ($game->icon_path && Storage::disk('public')->exists($game->icon_path)) {
            Storage::disk('public')->delete($game->icon_path);
        }
        
        // Eliminar el registro del juego
        $game->delete();
        
        // Devolver respuesta de éxito
        return response()->json([
            'message' => 'Juego eliminado correctamente'
        ]);
    }
}
