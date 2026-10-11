<?php
// Fuerza el tipado estricto para evitar conversiones implicitas de tipos.
declare(strict_types=1);

ini_set('display_errors', '0');          // produccion: nunca mostrar errores al usuario
ini_set('log_errors', '1');              // se registran en el log del servidor
error_reporting(E_ALL);
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Carga las variables desde un archivo .env (solo para pruebas locales).
// En la nube ese archivo no existe y se usan las variables que define el proveedor.
function cargarEnv(string $ruta): void
{
    if (!is_file($ruta)) {
        return;                                              // sin archivo: no hace nada
    }
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
            continue;                                        // ignora comentarios y lineas sin "="
        }
        [$nombre, $valor] = explode('=', $linea, 2);
        $nombre = trim($nombre);
        $valor  = trim($valor);
        if (strlen($valor) >= 2 && in_array($valor[0], ['"', "'"], true) && $valor[-1] === $valor[0]) {
            $valor = substr($valor, 1, -1);                  // quita comillas que rodean el valor
        }
        if (getenv($nombre) === false) {                     // una variable ya definida en el sistema tiene prioridad
            putenv("$nombre=$valor");
        }
    }
}
cargarEnv(__DIR__ . '/../../.env');                         // busca .env en la carpeta raíz del proyecto

// Listas fijas de la app: el servidor las usa para validar y el frontend para armar los formularios
const PISOS = ['Planta Baja', 'Primer Piso', 'Segundo Piso', 'Pieza Exterior'];
const ACTIVIDADES = [                    // actividad => destinos permitidos
    'Estudiar'      => ['Biblioteca', 'Primaria', 'Secundaria', 'Universidad'],
    'Trabajo'       => ['Carpinteria', 'Electricidad', 'Cocina', 'Zoom'],
    'Sanidad'       => ['Dentista', 'Medico', 'Psicologo'],
    'Visita'        => ['Familiar', 'Intima', 'Asistencia Legal'],
    'Cursos Varios' => ['Carpinteria', 'Artesania', 'Computacion', 'Peluqueria', 'Panaderia', 'Electricidad', 'Seguridad e Higiene'],
    'Traslado'      => ['Hospital', 'Juzgado'],
];

// Error controlado de la API: lleva el codigo HTTP y, opcionalmente, el detalle por campo
// Excepcion propia: lleva el codigo HTTP y el detalle por campo; convierte a JSON
class ApiError extends Exception
{
    public function __construct(public int $codigoHttp, string $mensaje, public array $detalles = [])
    {
        parent::__construct($mensaje);
    }
}

// Envia una respuesta JSON y termina
function responder(int $codigoHttp, array $datos): never
{
    // Fija el codigo HTTP de la respuesta (200, 201, 404...)
    http_response_code($codigoHttp);
    // Avisa al navegador que el cuerpo es JSON
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    // Convierte el array a JSON; JSON_UNESCAPED_UNICODE conserva tildes y ñ
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    // Corta la ejecucion: despues de responder no se hace nada mas
    exit;
}

// Conexion PDO (consultas preparadas reales, errores como excepciones)
function db(): PDO
{
    // static: la conexion se crea una sola vez por pedido y se reutiliza
    static $conexion = null;
    if ($conexion) {
        return $conexion;
    }
    // Cadena de conexion: host, puerto y nombre de la base salen de variables de entorno (getenv)
    $cadenaConexion = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: 'localhost', getenv('DB_PORT') ?: '3306', getenv('DB_NAME') ?: '');
    $opcionesPdo = [
        // Los errores de la base lanzan excepciones (se convierte en 500)
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // false = consultas preparadas reales de MySQL: protege contra inyeccion SQL
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (getenv('DB_SSL') === '1') {      // bases en la nube suelen exigir TLS
        $opcionesPdo[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
    }
    // Crea la conexion; usuario y contraseña tambien vienen del entorno, nunca del codigo
    return $conexion = new PDO($cadenaConexion, getenv('DB_USER') ?: '', getenv('DB_PASS') ?: '', $opcionesPdo);
}

// Sesion por cookie: HttpOnly, SameSite y Secure cuando se accede por HTTPS
function iniciarSesion(): void
{
    $conexionSegura = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    // httponly: JavaScript no puede leer la cookie; samesite: frena ataques CSRF; secure: solo viaja por HTTPS
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $conexionSegura, 'lifetime' => 0]);
    session_start();
}

// corta con 401 si no hay una sesion iniciada
function exigirLogin(): array
{
    if (empty($_SESSION['usuario'])) {
        throw new ApiError(401, 'Debe iniciar sesion');
    }
    return $_SESSION;
}

// Corta con 403 si el rol del usuario no esta permitido
function exigirRol(array $roles): void
{
    if (!in_array(exigirLogin()['rol'], $roles, true)) {
        throw new ApiError(403, 'Su rol no permite esta accion');
    }
}

// Lee el cuerpo JSON; 400 si esta mal formado
function leerJson(): array
{
    $cuerpoCrudo = file_get_contents('php://input');
    // Convierte el JSON recibido en array (devuelve null si esta mal formado)
    $datos = json_decode($cuerpoCrudo ?: '', true);
    // JSON ilegible o vacio → 400
    if (!is_array($datos)) {
        throw new ApiError(400, 'El cuerpo debe ser un JSON valido');
    }
    return $datos;
}
