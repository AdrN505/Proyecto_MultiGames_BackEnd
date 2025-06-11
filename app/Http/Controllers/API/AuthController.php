<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Controlador de autenticación de usuarios
 * 
 * Maneja el registro, inicio de sesión, cierre de sesión y obtención de datos del usuario.
 * Incluye medidas de seguridad como rate limiting, sanitización de datos y validaciones estrictas.
 * 
 */
class AuthController extends Controller
{
    /**
     * Constantes de límites de seguridad
     * 
     * Estas constantes definen los límites máximos y mínimos para varios campos
     * con el fin de prevenir ataques y asegurar la integridad de los datos.
     */
    const EMAIL_MAX_LENGTH = 255;              // Longitud máxima para emails
    const PASSWORD_MIN_LENGTH = 6;             // Longitud mínima para contraseñas
    const PASSWORD_MAX_LENGTH = 128;           // Longitud máxima para contraseñas
    const USERNAME_MIN_LENGTH = 2;             // Longitud mínima para nombres de usuario
    const USERNAME_MAX_LENGTH = 50;            // Longitud máxima para nombres de usuario
    const IMAGE_MAX_SIZE = 2048;               // Tamaño máximo de imagen en KB (2MB)
    const MAX_LOGIN_ATTEMPTS = 5;              // Número máximo de intentos de login
    const LOGIN_TIMEOUT = 300;                 // Tiempo de bloqueo en segundos (5 minutos)

    /**
     * Registro de un nuevo usuario
     *
     * Procesa el registro de nuevos usuarios con validaciones estrictas de seguridad,
     * rate limiting para prevenir spam, sanitización de datos y almacenamiento seguro
     * de imágenes de perfil opcionales.
     *
     */
    public function register(Request $request)
    {
        try {
            // Implementación de rate limiting para prevenir spam en registros
            // Limita a 3 intentos por IP cada 15 minutos
            $key = 'register_attempts:' . $request->ip();
            if (RateLimiter::tooManyAttempts($key, 3)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json([
                    'message' => 'Demasiados intentos de registro. Intenta de nuevo en ' . ceil($seconds / 60) . ' minutos.',
                    'errors' => ['rate_limit' => ['Límite de intentos excedido']]
                ], 429);
            }

            // Registrar el intento con un TTL de 15 minutos
            RateLimiter::hit($key, 900);

            // Sanitizar datos de entrada para prevenir inyecciones
            $sanitizedData = $this->sanitizeInput($request->all());

            // Validación exhaustiva de todos los campos de entrada
            $validator = Validator::make($sanitizedData, [
                'email' => [
                    'required',
                    'string',
                    'email:rfc,dns',                    
                    'max:' . self::EMAIL_MAX_LENGTH,
                    'unique:users,email',               // Verificar que el email no exista
                    'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', // Regex adicional
                    function ($attribute, $value, $fail) {
                        // Verificación personalizada de patrones peligrosos
                        if ($this->containsDangerousPatterns($value)) {
                            $fail('El correo electrónico contiene caracteres no permitidos.');
                        }
                    },
                ],
                'password' => [
                    'required',
                    'string',
                    'min:' . self::PASSWORD_MIN_LENGTH,
                    'max:' . self::PASSWORD_MAX_LENGTH,
                    'regex:/^[a-zA-Z0-9]+$/',          // Solo letras y números por seguridad
                ],
                'username' => [
                    'required',
                    'string',
                    'min:' . self::USERNAME_MIN_LENGTH,
                    'max:' . self::USERNAME_MAX_LENGTH,
                    'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ0-9\s\-_]+$/', // Permite caracteres especiales del español (importante para mi nombre Adrián Núñez)
                    function ($attribute, $value, $fail) {
                        // Validaciones personalizadas para el nombre de usuario
                        if ($this->containsDangerousPatterns($value)) {
                            $fail('El nombre de usuario contiene caracteres no permitidos.');
                        }
                        // No permitir espacios al inicio o final
                        if (preg_match('/^\s+|\s+$/', $value)) {
                            $fail('El nombre de usuario no puede empezar o terminar con espacios.');
                        }
                        // No permitir espacios múltiples consecutivos
                        if (preg_match('/\s{2,}/', $value)) {
                            $fail('El nombre de usuario no puede contener espacios múltiples consecutivos.');
                        }
                        // Verificar si el nombre es inapropiado
                        if ($this->isInappropriateUsername($value)) {
                            $fail('El nombre de usuario no es apropiado.');
                        }
                    },
                ],
                'imagen' => [
                    'nullable',                         // Campo opcional
                    'image',                           // Debe ser una imagen
                    'max:' . self::IMAGE_MAX_SIZE,     // Tamaño máximo
                    'mimes:jpeg,jpg,png,gif,webp',     // Tipos MIME permitidos
                    'dimensions:max_width=5000,max_height=5000', // Dimensiones máximas
                    function ($attribute, $value, $fail) {
                        // Validación adicional del archivo de imagen
                        if ($value && !$this->isValidImageFile($value)) {
                            $fail('El archivo de imagen no es válido o está corrupto.');
                        }
                    },
                ],
            ], [
                // Mensajes de error personalizados
                'email.unique' => 'El correo electrónico ya está en uso.',
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'El formato del correo electrónico no es válido.',
                'email.max' => 'El correo electrónico es demasiado largo.',
                'password.required' => 'La contraseña es obligatoria.',
                'password.min' => 'La contraseña debe tener al menos ' . self::PASSWORD_MIN_LENGTH . ' caracteres.',
                'password.max' => 'La contraseña no puede exceder ' . self::PASSWORD_MAX_LENGTH . ' caracteres.',
                'password.regex' => 'La contraseña solo puede contener letras y números.',
                'username.required' => 'El nombre de usuario es obligatorio.',
                'username.min' => 'El nombre de usuario debe tener al menos ' . self::USERNAME_MIN_LENGTH . ' caracteres.',
                'username.max' => 'El nombre de usuario no puede exceder ' . self::USERNAME_MAX_LENGTH . ' caracteres.',
                'username.regex' => 'El nombre de usuario solo puede contener letras, números, espacios, guiones (-) y guiones bajos (_).',
                'imagen.image' => 'El archivo debe ser una imagen.',
                'imagen.max' => 'La imagen no debe superar los ' . (self::IMAGE_MAX_SIZE / 1024) . 'MB.',
                'imagen.mimes' => 'La imagen debe ser de tipo: jpeg, jpg, png, gif o webp.',
                'imagen.dimensions' => 'Las dimensiones de la imagen son demasiado grandes.',
            ]);

            // Si hay errores de validación, retornar con código 422
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Crear nueva instancia de usuario con datos validados y sanitizados
            $user = new User();
            $user->email = strtolower(trim($sanitizedData['email']));     // Email en minúsculas
            $user->password = Hash::make($sanitizedData['password']);      // Hash seguro de la contraseña
            $user->username = trim($sanitizedData['username']);            // Username limpio
            
            // Procesar imagen de perfil si fue proporcionada
            if ($request->hasFile('imagen') && $request->file('imagen')->isValid()) {
                $imagePath = $this->processAndStoreImage($request->file('imagen'));
                if ($imagePath) {
                    // Generar URL completa para la imagen
                    $user->imagen_url = url(Storage::url($imagePath));
                }
            }
            
            // Guardar usuario en la base de datos
            $user->save();
            
            // Limpiar rate limiting tras registro exitoso
            RateLimiter::clear($key);
            
            // Generar token de autenticación usando Laravel Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;
            
            // Registrar evento de auditoría para seguimiento de seguridad
            \Log::info('Usuario registrado exitosamente', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            // Retornar respuesta exitosa con datos del usuario y token
            return response()->json([
                'message' => 'Usuario registrado exitosamente',
                'user' => $user,
                'token' => $token
            ], 201);

        } catch (\Exception $e) {
            // Log detallado del error para debugging sin exponer información sensible
            \Log::error('Error en registro: ' . $e->getMessage(), [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Retornar error genérico al cliente para no exponer detalles internos
            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => 'Ocurrió un error inesperado. Por favor, intenta más tarde.'
            ], 500);
        }
    }

    /**
     * Iniciar sesión de usuario
     *
     * Autentica a un usuario existente verificando sus credenciales.
     * Incluye rate limiting para prevenir ataques de fuerza bruta
     * y logging de intentos fallidos para auditoría de seguridad.
     */
    public function login(Request $request)
    {
        try {
            // Rate limiting específico para intentos de login por IP
            // Permite máximo 5 intentos cada 5 minutos
            $key = 'login_attempts:' . $request->ip();
            if (RateLimiter::tooManyAttempts($key, self::MAX_LOGIN_ATTEMPTS)) {
                $seconds = RateLimiter::availableIn($key);
                return response()->json([
                    'message' => 'Demasiados intentos de inicio de sesión. Intenta de nuevo en ' . ceil($seconds / 60) . ' minutos.',
                    'errors' => ['auth' => ['Límite de intentos excedido']]
                ], 429);
            }

            // Sanitizar datos de entrada
            $sanitizedData = $this->sanitizeInput($request->all());

            // Validar formato de las credenciales de entrada
            $validator = Validator::make($sanitizedData, [
                'email' => [
                    'required',
                    'string',
                    'email', 
                    'max:' . self::EMAIL_MAX_LENGTH,
                    function ($attribute, $value, $fail) {
                        // Verificar patrones peligrosos en el email
                        if ($this->containsDangerousPatterns($value)) {
                            $fail('El correo electrónico contiene caracteres no permitidos.');
                        }
                    },
                ],
                'password' => [
                    'required',
                    'string',
                    'max:' . self::PASSWORD_MAX_LENGTH, // Prevenir payloads muy largos
                ],
            ], [
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'El formato del correo electrónico no es válido.',
                'password.required' => 'La contraseña es obligatoria.',
            ]);

            // Si hay errores de validación, incrementar contador de intentos fallidos
            if ($validator->fails()) {
                RateLimiter::hit($key, self::LOGIN_TIMEOUT);
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Preparar credenciales para autenticación
            $credentials = [
                'email' => strtolower(trim($sanitizedData['email'])), // Normalizar email
                'password' => $sanitizedData['password']
            ];

            // Intentar autenticación con las credenciales proporcionadas
            if (!Auth::attempt($credentials)) {
                // Incrementar contador de intentos fallidos
                RateLimiter::hit($key, self::LOGIN_TIMEOUT);
                
                // Registrar intento fallido para auditoría de seguridad
                \Log::warning('Intento de login fallido', [
                    'email' => $credentials['email'],
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
                
                // Retornar error genérico para no dar pistas sobre usuarios existentes
                return response()->json([
                    'message' => 'Credenciales inválidas',
                    'errors' => ['auth' => ['El correo electrónico o la contraseña son incorrectos.']]
                ], 401);
            }

            // Limpiar rate limiting tras autenticación exitosa
            RateLimiter::clear($key);

            // Obtener usuario autenticado y generar token
            $user = User::where('email', $credentials['email'])->firstOrFail();
            $token = $user->createToken('auth_token')->plainTextToken;
            
            // Registrar login exitoso para auditoría
            \Log::info('Login exitoso', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            // Retornar datos del usuario y token de autenticación
            return response()->json([
                'message' => 'Inicio de sesión exitoso',
                'user' => $user,
                'token' => $token
            ]);

        } catch (\Exception $e) {
            // Log detallado del error para debugging
            \Log::error('Error en login: ' . $e->getMessage(), [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Retornar error genérico al cliente
            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => 'Ocurrió un error inesperado. Por favor, intenta más tarde.'
            ], 500);
        }
    }

    /**
     * Cerrar sesión del usuario
     *
     * Revoca todos los tokens de autenticación del usuario actual,
     * efectivamente cerrando la sesión.
     */
    public function logout(Request $request)
    {
        try {
            // Registrar evento de logout para auditoría
            \Log::info('Usuario cerró sesión', [
                'user_id' => $request->user()->id,
                'ip' => $request->ip()
            ]);
            
            // Revocar todos los tokens de autenticación del usuario
            // Esto cerrará la sesión en todos los dispositivos
            $request->user()->tokens()->delete();
            
            return response()->json([
                'message' => 'Sesión cerrada exitosamente'
            ]);

        } catch (\Exception $e) {
            // Log del error sin exponer detalles al cliente
            \Log::error('Error en logout: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al cerrar sesión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener información del usuario autenticado
     *
     * Retorna los datos del usuario que está actualmente autenticado.
     * Útil para verificar el estado de la sesión y obtener datos actualizados.
     */
    public function user(Request $request)
    {
        try {
            // Retornar datos del usuario autenticado
            return response()->json([
                'user' => $request->user()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al obtener usuario: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error al obtener información del usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sanitizar datos de entrada
     *
     * Limpia los datos de entrada removiendo patrones potencialmente peligrosos
     * que podrían usarse para inyecciones XSS o scripts maliciosos.
     * Se enfoca en remover solo lo realmente peligroso para mantener la usabilidad.
     */
    private function sanitizeInput(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Remover etiquetas script completas (incluyendo contenido)
                $value = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $value);
                
                // Remover protocolos javascript: que pueden ejecutar código
                $value = preg_replace('/javascript:/i', '', $value);
                
                // Remover manejadores de eventos HTML (onclick, onload, etc.)
                $value = preg_replace('/on\w+=/i', '', $value);
                
                // Limpiar espacios en blanco al inicio y final
                $sanitized[$key] = trim($value);
            } else {
                // Para datos no string, mantener el valor original
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Verificar patrones peligrosos en el input
     *
     * Analiza el texto de entrada en busca de patrones que podrían indicar
     * intentos de inyección XSS, inyección SQL u otros ataques comunes.
     *
     * @param string $input Texto a analizar
     * @return bool true si contiene patrones peligrosos, false en caso contrario
     */
    private function containsDangerousPatterns(string $input): bool
    {
        // Patrones conocidos que indican posibles ataques
        $dangerousPatterns = [
            // Patrones XSS
            '/<script/i',           // Etiquetas script
            '/javascript:/i',       // Protocolo javascript
            '/on\w+=/i',           // Manejadores de eventos HTML
            '/<iframe/i',          // Iframes maliciosos
            '/<object/i',          // Objetos embebidos
            '/<embed/i',           // Contenido embebido
            '/vbscript:/i',        // VBScript
            '/\0/',                // Bytes nulos (null byte attacks)
            
            // Patrones básicos de inyección SQL
            '/union.*select/i',    // Ataques UNION SELECT
            '/select.*from/i',     // Consultas SELECT básicas
            '/insert.*into/i',     // Inserciones maliciosas
            '/delete.*from/i',     // Borrados maliciosos
            '/drop.*table/i',      // Eliminación de tablas
        ];
        
        // Verificar cada patrón contra el input
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Verificar nombres de usuario inapropiados
     *
     * Comprueba si un nombre de usuario corresponde a palabras reservadas
     * del sistema o términos que podrían causar confusión o problemas.
     */
    private function isInappropriateUsername(string $username): bool
    {
        // Lista de palabras reservadas y términos problemáticos
        $inappropriateWords = [
            // Términos del sistema
            'admin', 'administrator', 'root', 'system', 'null', 'undefined',
            // Términos de prueba
            'test', 'demo', 'example', 'guest', 'anonymous', 'user', 'default'
        ];
        
        $lowercaseUsername = strtolower($username);
        
        // Verificar si el username es exactamente igual a una palabra reservada
        return in_array($lowercaseUsername, $inappropriateWords);
    }

    /**
     * Validar archivo de imagen
     *
     * Verifica que un archivo subido sea realmente una imagen válida
     * y no un archivo malicioso disfrazado. Utiliza getimagesize() para
     * verificación profunda del contenido del archivo.
     */
    private function isValidImageFile($file): bool
    {
        try {
            // Usar getimagesize() para verificar que es realmente una imagen
            // Esta función lee los primeros bytes del archivo para determinar el tipo
            $imageInfo = getimagesize($file->getPathname());
            
            if ($imageInfo === false) {
                // No es una imagen válida
                return false;
            }
            
            // Lista de tipos MIME permitidos para imágenes
            $allowedMimes = [
                'image/jpeg',
                'image/jpg', 
                'image/png', 
                'image/gif', 
                'image/webp'
            ];
            
            // Verificar que el tipo MIME esté en la lista permitida
            if (!in_array($imageInfo['mime'], $allowedMimes)) {
                return false;
            }
            
            return true;

        } catch (\Exception $e) {
            // En caso de cualquier error, considerar el archivo como inválido
            return false;
        }
    }

    /**
     * Procesar y almacenar imagen de forma segura
     *
     * Toma un archivo de imagen subido, genera un nombre único y seguro,
     * y lo almacena en el directorio designado para avatares.
     */
    private function processAndStoreImage($file): ?string
    {
        try {
            // Generar nombre único para evitar colisiones y problemas de seguridad
            $filename = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            // Almacenar en el directorio 'avatars' del disco 'public'
            $path = $file->storeAs('avatars', $filename, 'public');
            
            return $path;

        } catch (\Exception $e) {
            // Log del error para debugging
            \Log::error('Error al procesar imagen: ' . $e->getMessage());
            return null;
        }
    }
}