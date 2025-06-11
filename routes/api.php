<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\GameController;
use App\Http\Controllers\API\StatisticsController;
use App\Http\Controllers\API\GameHistoryController;
use App\Http\Controllers\API\RelationshipController;
use App\Http\Controllers\API\ChatController;

// Rutas públicas
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    // Usuario
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user/profile', [UserController::class, 'getProfile']);
    Route::post('/user/profile-image', [UserController::class, 'updateProfileImage']);
    Route::delete('/user/delete-account', [UserController::class, 'deleteAccount']);
    
    // Gestión de usuarios
    Route::get('/usuarios', [UserController::class, 'getUsers']);
    
    // Amigos
    Route::get('/friends', [RelationshipController::class, 'getFriends']);
    Route::get('/friends/pending', [RelationshipController::class, 'getPendingRequests']);
    Route::post('/friends/request/{userId}', [RelationshipController::class, 'sendFriendRequest']);
    Route::post('/friends/accept/{userId}', [RelationshipController::class, 'acceptFriendRequest']);
    Route::post('/friends/reject/{userId}', [RelationshipController::class, 'rejectFriendRequest']);
    Route::delete('/friends/{userId}', [RelationshipController::class, 'removeFriend']);
    
    // Usuarios bloqueados
    Route::get('/users/blocked', [RelationshipController::class, 'getBlockedUsers']);
    Route::post('/users/block/{userId}', [RelationshipController::class, 'blockUser']);
    Route::post('/users/unblock/{userId}', [RelationshipController::class, 'unblockUser']);
    
    // Juegos
    Route::get('/games', [GameController::class, 'getAllGames']);
    Route::post('/games/{gameId}/record-result', [GameController::class, 'recordGameResult']);
    
    // Estadísticas
    Route::get('/statistics', [StatisticsController::class, 'getAllStatistics']);
    Route::get('/statistics/offline', [StatisticsController::class, 'getOfflineStatistics']);
    Route::get('/statistics/online', [StatisticsController::class, 'getOnlineStatistics']);
    Route::get('/statistics/game/{gameId}', [StatisticsController::class, 'getGameStatistics']);
    
    // Historial de partidas
    Route::get('/game-history', [GameHistoryController::class, 'getUserHistory']);
    Route::get('/game-history/{gameId}', [GameHistoryController::class, 'getGameHistory']);

    // Rutas del chat
    Route::prefix('chat')->group(function () {
        // Obtener todos los chats del usuario
        Route::get('/', [ChatController::class, 'getChats']);
        // Iniciar un nuevo chat con un usuario
        Route::post('/start', [ChatController::class, 'startChat']);
        // Obtener mensajes de un chat específico
        Route::get('/{chatId}/messages', [ChatController::class, 'getChatMessages']);
        Route::post('/message', [ChatController::class, 'sendMessage']);
        // Marcar mensajes como leídos
        Route::patch('/{chatId}/read', [ChatController::class, 'markAsRead']);
        // Obtener contador de mensajes no leídos
        Route::get('/unread-count', [ChatController::class, 'getUnreadCount']);
        Route::delete('/{chatId}', [ChatController::class, 'deleteChat']);
    });

    // Admin routes
    Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
        // Game management
        Route::get('/games', [App\Http\Controllers\Admin\GameAdminController::class, 'index']);
        Route::post('/games', [App\Http\Controllers\Admin\GameAdminController::class, 'store']);
        Route::put('/games/{id}', [App\Http\Controllers\Admin\GameAdminController::class, 'update']);
        Route::delete('/games/{id}', [App\Http\Controllers\Admin\GameAdminController::class, 'destroy']);
    });
});