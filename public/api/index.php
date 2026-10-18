<?php
declare(strict_types=1); // fuerza tipado estricto

// Carga las variables de entorno
require __DIR__ . '/config.php';

// Aplicamos la configuracion del CORS
require __DIR__ . '/cors.php';
aplicarCors();

// Carga las dependencias de la API antes de despachar las rutas.
require __DIR__ . '/errores.php';
require __DIR__ . '/conexion.php';
require __DIR__ . '/utilidades.php';
require __DIR__ . '/login.php';
require __DIR__ . '/internos.php';
require __DIR__ . '/usuarios.php';
require __DIR__ . '/historial.php';
require __DIR__ . '/actividad.php';
require __DIR__ . '/servicios.php';
require __DIR__ . '/init_db.php';

$fase = 'inicializacion';
try {
    exigirEnv('DB_HOST');
    exigirEnv('DB_NAME');
    exigirEnv('DB_USER');
    asegurarTablas();

    $fase = 'procesamiento de solicitud';
    // Abre o recupera la sesion por cookie
    iniciarSesion();
    // Metodo HTTP del pedido: GET, POST, PUT o DELETE
    $metodo = $_SERVER['REQUEST_METHOD'];
    // Quita el prefijo /api: /api/internos/5 --> /internos/5
    $ruta   = preg_replace('#^.*/api#', '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    // Divide la ruta en segmentos: ['internos', '5']
    $segmentosRuta      = array_values(array_filter(explode('/', $ruta), fn($segmento) => $segmento !== ''));
    // recurso = 'internos', id = '5', accion = 'salida' (si existe)
    [$recurso, $id, $accion] = [$segmentosRuta[0] ?? '', $segmentosRuta[1] ?? null, $segmentosRuta[2] ?? null];

    // Sesion
    if ($recurso === 'login' && $metodo === 'POST') {
        manejarLogin();
    }

    if ($recurso === 'logout' && $metodo === 'POST') {
        manejarLogout();
    }

    if ($recurso === 'sesion' && $metodo === 'GET') {
        manejarSesion();
    }

    if ($recurso === 'config' && $metodo === 'GET') {
        manejarConfig();
    }

    if ($recurso === 'servicios' && $metodo === 'GET') {
        manejarServicios();
    }

    if ($recurso === 'historial' && $metodo === 'GET') {
        manejarHistorial();
    }

    if ($recurso === 'registro-actividades' && $metodo === 'GET') {
        manejarRegistroActividades();
    }    

    if ($recurso === 'usuarios') {
        manejarUsuarios($metodo, $id);
    }

    if ($recurso === 'internos') {
        manejarInternos($metodo, $id, $accion);
    }

    // Ninguna ruta coincidio --> 404
    throw new ApiError(404, 'Ruta no encontrada');

// Errores esperados: se responden con su propio codigo (400, 401, 403, 404, 409, 422)
} catch (ApiError $errorApi) {
    if ($errorApi->codigoHttp >= 500) {
        error_log(sprintf('Fallo de %s (HTTP %d): %s', $fase, $errorApi->codigoHttp, $errorApi->getMessage()));
    }
    responder($errorApi->codigoHttp, ['error' => $errorApi->getMessage()] + ($errorApi->detalles ? ['detalles' => $errorApi->detalles] : []));
// Cualquier otro fallo: 500 generico; el detalle va al log del servidor, nunca al usuario
} catch (Throwable $falla) {
    $mensaje = $falla->getMessage();
    foreach (['DB_USER', 'DB_PASS', 'ADMIN_PASSWORD'] as $variableSecreta) {
        $secreto = getenv($variableSecreta);
        if ($secreto !== false && $secreto !== '') {
            $mensaje = str_replace($secreto, '[REDACTED]', $mensaje);
        }
    }
    error_log(sprintf(
        'Fallo de %s (%s) en %s:%d: %s',
        $fase,
        get_class($falla),
        basename($falla->getFile()),
        $falla->getLine(),
        $mensaje
    ));
    responder(500, ['error' => 'Error interno del servidor']);
}
