
# 🎮 MultiGames API - Laravel Backend

Una API REST completa para una plataforma de juegos sociales construida con Laravel. Incluye sistema de autenticación, gestión de amistades, chat en tiempo real, estadísticas de juegos y administración de contenido.

## 📋 Tabla de Contenidos

* [Descripción](https://claude.ai/chat/0ab57b97-01a4-4f35-9126-30ed126afeb6#-descripci%C3%B3n)
* [Requisitos del Sistema](https://claude.ai/chat/0ab57b97-01a4-4f35-9126-30ed126afeb6#-requisitos-del-sistema)
* [Instalación Paso a Paso](https://claude.ai/chat/0ab57b97-01a4-4f35-9126-30ed126afeb6#-instalaci%C3%B3n-paso-a-paso)
* [Configuración](https://claude.ai/chat/0ab57b97-01a4-4f35-9126-30ed126afeb6#-configuraci%C3%B3n)
* [Estructura del Proyecto](https://claude.ai/chat/0ab57b97-01a4-4f35-9126-30ed126afeb6#-estructura-del-proyecto)
* [Endpoints de la API](https://claude.ai/chat/0ab57b97-01a4-4f35-9126-30ed126afeb6#-endpoints-de-la-api)
* [Base de Datos](https://claude.ai/chat/0ab57b97-01a4-4f35-9126-30ed126afeb6#-base-de-datos)
* [Testing](https://claude.ai/chat/0ab57b97-01a4-4f35-9126-30ed126afeb6#-testing)
* [Solución de Problemas](https://claude.ai/chat/0ab57b97-01a4-4f35-9126-30ed126afeb6#-soluci%C3%B3n-de-problemas)

## 🎯 Descripción

**MultiGames API** es el backend de una plataforma social de juegos que permite:

* 🔐 **Autenticación segura** con Laravel Sanctum
* 👥 **Sistema social completo** (amigos, bloqueos, solicitudes)
* 💬 **Chat en tiempo real** entre usuarios
* 📊 **Estadísticas detalladas** de rendimiento en juegos
* 🎮 **Gestión de juegos** y historial de partidas
* 👨‍💼 **Panel administrativo** para gestión de contenido

## ⚙️ Requisitos del Sistema

### Software Necesario

* **PHP** : 8.1 o superior
* **Composer** : 2.x
* **MySQL** : 8.0+ (o MariaDB 10.4+)
* **Node.js** : 16+ (para compilar assets si es necesario)

### Extensiones PHP Requeridas

```bash
php -m | grep -E "(bcmath|ctype|fileinfo|json|mbstring|openssl|pdo|tokenizer|xml|gd)"
```

Debe mostrar todas estas extensiones instaladas:

* `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `gd`

## 🚀 Instalación Paso a Paso

### 1. Clonar o Descargar el Proyecto

```bash
# Si tienes acceso al repositorio
git clone [URL_DEL_REPOSITORIO] multigames-api
cd multigames-api

# O descomprime el archivo ZIP en una carpeta llamada 'multigames-api'
```

### 2. Instalar Dependencias de PHP

```bash
composer install
```

**Si hay errores:**

* Verifica que Composer esté instalado: `composer --version`
* Actualiza Composer: `composer self-update`

### 3. Configurar Variables de Entorno

#### Copiar archivo de configuración:

```bash
cp .env.example .env
```

#### Generar clave de aplicación:

```bash
php artisan key:generate
```

### 4. Configurar Base de Datos

#### Crear la base de datos:

```sql
-- Conéctate a MySQL
mysql -u root -p

-- Crear base de datos
CREATE DATABASE multigames_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Crear usuario (opcional pero recomendado)
CREATE USER 'multigames_user'@'localhost' IDENTIFIED BY 'tu_password_segura';
GRANT ALL PRIVILEGES ON multigames_db.* TO 'multigames_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### Configurar .env con datos de base de datos:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=multigames_db
DB_USERNAME=multigames_user
DB_PASSWORD=tu_password_segura
```

### 5. Ejecutar Migraciones y Seeders

```bash
# Ejecutar migraciones
php artisan migrate

# Si quieres datos de prueba
php artisan db:seed
```

### 6. Configurar Storage

```bash
# Crear enlace simbólico para archivos públicos
php artisan storage:link

# Crear directorios necesarios
mkdir -p storage/app/public/avatars
mkdir -p storage/app/public/game-assets
```

### 7. Iniciar el Servidor

```bash
php artisan serve

# El servidor estará disponible en: http://localhost:8000
```

## 🔧 Configuración

### Archivo .env Completo

Reemplaza tu archivo `.env` con esta configuración adaptada:

```env
# === CONFIGURACIÓN DE APLICACIÓN ===
APP_NAME="MultiGames API"
APP_ENV=local
APP_KEY=base64:6Bgf6/7Rj5HYcmp8cQCVClBwQwoAtY7C0zCckd4cncc=
APP_DEBUG=true
APP_URL=http://localhost:8000

# === LOGS ===
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# === BASE DE DATOS ===
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=multigames_db
DB_USERNAME=root
DB_PASSWORD=

# === CACHE Y SESIONES ===
BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

# === REDIS (Opcional - para mejor rendimiento) ===
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# === EMAIL (Para recuperación de contraseñas) ===
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

# === SANCTUM (Autenticación) ===
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:5173,192.168.0.33:5173

# === FRONTEND URLS ===
FRONTEND_URL=http://localhost:3000
```

### Configuración CORS

El archivo `config/cors.php` ya está configurado para desarrollo:

```php
<?php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:5173',    # Vite dev server
        'http://localhost:3000',    # React/Next.js
        'http://192.168.0.33:5173', # Tu IP local
        'http://192.168.0.33:3000',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

**📝 Nota:** Cambia las IPs `192.168.0.33` por tu IP local real.

## 📁 Estructura del Proyecto

```
multigames-api/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── API/              # 🔹 Controladores principales
│   │       │   ├── AuthController.php
│   │       │   ├── ChatController.php
│   │       │   ├── GameController.php
│   │       │   ├── GameHistoryController.php
│   │       │   ├── RelationshipController.php
│   │       │   ├── StatisticsController.php
│   │       │   └── UserController.php
│   │       └── Admin/            # 🔹 Controladores admin
│   │           └── GameAdminController.php
│   └── Models/                   # 🔹 Modelos de datos
│       ├── User.php
│       ├── Chat.php
│       ├── ChatMessage.php
│       ├── Friendship.php
│       ├── BlockedUser.php
│       ├── Game.php
│       ├── GameStatistic.php
│       └── GameHistory.php
├── config/
│   ├── cors.php                  # 🔹 Configuración CORS
│   └── sanctum.php               # 🔹 Configuración autenticación
├── database/
│   ├── migrations/               # 🔹 Migraciones de BD
│   └── seeders/                  # 🔹 Datos de prueba
├── routes/
│   └── api.php                   # 🔹 Rutas de la API
├── storage/
│   └── app/public/
│       ├── avatars/              # 🔹 Imágenes de perfil
│       └── game-assets/          # 🔹 Assets de juegos
└── .env                          # 🔹 Variables de entorno
```

## 🔌 Endpoints de la API

### 🔐 Autenticación

```http
POST   /api/register              # Registro de usuario
POST   /api/login                 # Inicio de sesión
POST   /api/logout                # Cerrar sesión (requiere auth)
GET    /api/user                  # Datos del usuario autenticado
```

### 👤 Gestión de Usuario

```http
GET    /api/user/profile          # Obtener perfil
POST   /api/user/profile-image    # Subir imagen de perfil
DELETE /api/user/delete-account   # Eliminar cuenta
GET    /api/usuarios              # Lista de usuarios
```

### 👥 Sistema Social (Amistades)

```http
GET    /api/friends               # Lista de amigos
GET    /api/friends/pending       # Solicitudes pendientes
POST   /api/friends/request/{id}  # Enviar solicitud de amistad
POST   /api/friends/accept/{id}   # Aceptar solicitud
POST   /api/friends/reject/{id}   # Rechazar solicitud
DELETE /api/friends/{id}          # Eliminar amistad
```

### 🚫 Bloqueo de Usuarios

```http
GET    /api/users/blocked         # Lista de usuarios bloqueados
POST   /api/users/block/{id}      # Bloquear usuario
POST   /api/users/unblock/{id}    # Desbloquear usuario
```

### 💬 Sistema de Chat

```http
GET    /api/chat                  # Lista de conversaciones
POST   /api/chat/start            # Iniciar nueva conversación
GET    /api/chat/{id}/messages    # Mensajes de conversación
POST   /api/chat/message          # Enviar mensaje
PATCH  /api/chat/{id}/read        # Marcar como leído
GET    /api/chat/unread-count     # Contador de no leídos
DELETE /api/chat/{id}             # Eliminar conversación
```

### 🎮 Gestión de Juegos

```http
GET    /api/games                 # Lista de juegos disponibles
POST   /api/games/{id}/record-result  # Registrar resultado de partida
GET    /api/game-history          # Historial del usuario
GET    /api/game-history/{gameId} # Historial por juego
```

### 📊 Estadísticas

```http
GET    /api/statistics            # Todas las estadísticas
GET    /api/statistics/offline    # Estadísticas offline
GET    /api/statistics/online     # Estadísticas online
GET    /api/statistics/game/{id}  # Estadísticas por juego
```

### 👨‍💼 Administración

```http
GET    /api/admin/games           # Gestión de juegos (admin)
POST   /api/admin/games           # Crear juego
PUT    /api/admin/games/{id}      # Actualizar juego
DELETE /api/admin/games/{id}      # Eliminar juego
```

## 🗄️ Base de Datos

### Migraciones Principales

El proyecto incluye las siguientes tablas:

1. **users** - Datos de usuarios
2. **personal_access_tokens** - Tokens de autenticación
3. **games** - Catálogo de juegos
4. **friendships** - Relaciones de amistad
5. **blocked_users** - Usuarios bloqueados
6. **chats** - Conversaciones
7. **chat_messages** - Mensajes del chat
8. **game_statistics** - Estadísticas por juego
9. **game_history** - Historial de partidas

### Comandos Útiles de Base de Datos

```bash
# Ver estado de migraciones
php artisan migrate:status

# Ejecutar migraciones pendientes
php artisan migrate

# Rollback última migración
php artisan migrate:rollback

# Rollback todas las migraciones
php artisan migrate:reset

# Refresh completo (cuidado: borra todos los datos)
php artisan migrate:refresh --seed
```

## 🧪 Testing

### Probar la API

#### 1. Registro de Usuario

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Usuario Test",
    "email": "test@ejemplo.com",
    "username": "usuario_test",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

#### 2. Login

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "test@ejemplo.com",
    "password": "password123"
  }'
```

#### 3. Obtener Perfil (con token)

```bash
curl -X GET http://localhost:8000/api/user/profile \
  -H "Authorization: Bearer TU_TOKEN_AQUI" \
  -H "Accept: application/json"
```

### Verificar Funcionalidades

```bash
# Verificar que el servidor funciona
curl http://localhost:8000/api/user
# Debería devolver: {"message":"Unauthenticated."}

# Verificar rutas
php artisan route:list --path=api

# Verificar configuración
php artisan config:show database
```

## 🐛 Solución de Problemas

### Problemas Comunes

#### 1. Error "Class 'GD' not found"

```bash
# Ubuntu/Debian
sudo apt-get install php-gd

# CentOS/RHEL
sudo yum install php-gd

# Reiniciar servidor web
sudo systemctl restart apache2
# o
sudo systemctl restart nginx
```

#### 2. Error de permisos en storage

```bash
sudo chmod -R 775 storage
sudo chmod -R 775 bootstrap/cache
sudo chown -R www-data:www-data storage
sudo chown -R www-data:www-data bootstrap/cache
```

#### 3. Error "Key length too long" en MySQL

Añadir en `config/database.php`:

```php
'mysql' => [
    // ... otras configuraciones
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options' => [
        PDO::ATTR_EMULATE_PREPARES => true,
    ],
],
```

#### 4. CORS errors desde el frontend

Verificar que las URLs del frontend están en `config/cors.php`:

```php
'allowed_origins' => [
    'http://localhost:3000',
    'http://localhost:5173',
    'http://TU_IP:3000',
    'http://TU_IP:5173',
],
```

#### 5. Error "Sanctum CSRF token mismatch"

Añadir en `.env`:

```env
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:5173,TU_IP:3000
SESSION_DOMAIN=localhost
```

### Comandos de Limpieza

```bash
# Limpiar todas las caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimizar para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Verificar Configuración

```bash
# Verificar versión PHP
php -v

# Verificar extensiones PHP
php -m

# Verificar conexión a base de datos
php artisan tinker
>>> DB::connection()->getPdo();

# Verificar rutas
php artisan route:list
```

## 🔧 Personalización

### Modificar CORS para tu IP

1. Obtén tu IP local:

```bash
# Linux/Mac
ip addr show | grep "inet " | grep -v 127.0.0.1

# Windows
ipconfig | findstr "IPv4"
```

2. Actualiza `config/cors.php`:

```php
'allowed_origins' => [
    'http://localhost:3000',
    'http://localhost:5173',
    'http://TU_IP_AQUI:3000',
    'http://TU_IP_AQUI:5173',
],
```

### Cambiar Puerto del Servidor

```bash
# Cambiar puerto (ejemplo: 8080)
php artisan serve --port=8080

# Cambiar host también
php artisan serve --host=0.0.0.0 --port=8080
```

## 📞 Soporte

Si encuentras problemas durante la instalación:

1. Verifica que cumples todos los **requisitos del sistema**
2. Revisa la sección de **solución de problemas**
3. Consulta los **logs** en `storage/logs/laravel.log`
4. Verifica la **configuración de base de datos**

---

**🎮 ¡Tu MultiGames API está lista para funcionar!**

Ahora puedes conectar tu frontend a `http://localhost:8000/api/` y comenzar a usar todas las funcionalidades.
