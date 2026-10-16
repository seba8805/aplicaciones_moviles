<?php
declare(strict_types=1); // fuerza tipado estricto
// Carga la configuracion (base, sesion, helpers) y los servicios externos
require __DIR__ . '/config.php';
require __DIR__ . '/servicios.php';

// Utilidades de validacion
// Devuelve el texto recortado si el valor es un string; en el caso de array, numero, null devuelve ''. Evita errores con datos inesperados
function txt(mixed $valor): string
{
    return is_string($valor) ? trim($valor) : '';
}

// Normaliza mayusculas: 'juan CARLOS' --> 'Juan Carlos'
function nombrePropio(string $texto): string
{
    return mb_convert_case(mb_strtolower($texto), MB_CASE_TITLE);
}

// Devuelve datos limpios, errores por campo
// Validacion en el servidor
function validarInterno(array $datosRecibidos): array
{
    // Acumula los errores por campo para devolverlos todos juntos (422)
    $errores = [];
    // Retorna el texto limpio de espacios o vacío si el valor no es valido
    $legajo = txt($datosRecibidos['legajo'] ?? null);
    // Legajo: solo letras, numeros y guiones, de 1 a 20 caracteres
    if (!preg_match('/^[A-Za-z0-9-]{1,20}$/', $legajo)) {
        $errores['legajo'] = 'Legajo obligatorio (letras, numeros o guiones, max. 20)';
    }
    $datosLimpios = ['legajo' => $legajo];

    // Los tres campos de nombre se validan con la misma regla
    foreach (['apellido_paterno' => 'Apellido paterno', 'apellido_materno' => 'Apellido materno', 'nombres' => 'Nombres'] as $campo => $etiqueta) {
        $valorCampo = txt($datosRecibidos[$campo] ?? null);
        // Obligatorio, maximo 60 y solo letras (\p{L} admite tildes y ñ)
        if ($valorCampo === '' || mb_strlen($valorCampo) > 60 || !preg_match("/^[\p{L}' .-]+$/u", $valorCampo)) {
            $errores[$campo] = "$etiqueta es obligatorio (solo letras, max. 60)";
        }
        $datosLimpios[$campo] = nombrePropio($valorCampo);
    }

    $piso = txt($datosRecibidos['piso'] ?? null);
    // El piso debe ser uno de los permitidos (tecnica lista blanca)
    if (!in_array($piso, PISOS, true)) {
        $errores['piso'] = 'Piso invalido';
    }
    // Celda: entero entre 1 y 10
    $celda = filter_var($datosRecibidos['celda'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
    if ($celda === false) {
        $errores['celda'] = 'La celda debe ser un numero entre 1 y 10';
    }
    // Devuelve datos limpios, errores
    return [$datosLimpios + ['piso' => $piso, 'celda' => $celda], $errores];
}

// Consulta internos sus movimientos
// Consulta de internos con su ubicacion actual (salida pendiente = fuera de celda)
// Consulta base: une cada interno con su salida pendiente (regreso NULL) y calcula la columna 'ubicacion' (En celda / destino / En Transito)
const SQL_INTERNOS = "SELECT i.id, i.legajo, i.apellido_paterno, i.apellido_materno, i.nombres, i.piso, i.celda,
        m.actividad, m.subactividad, m.salida,
        CASE WHEN m.id IS NULL THEN 'En celda'
             WHEN m.actividad = 'Traslado' THEN CONCAT('En Tránsito ', m.subactividad)
             ELSE m.subactividad END AS ubicacion
    FROM internos i LEFT JOIN movimientos m ON m.interno_id = i.id AND m.regreso IS NULL";

// Busca un interno por id; si no existe lanza el 404
function buscarInterno(int $id): array
{
    $consulta = db()->prepare(SQL_INTERNOS . ' WHERE i.id = ?');
    $consulta->execute([$id]);
    // fetch() devuelve false si no hay fila → se lanza ApiError 404
    return $consulta->fetch() ?: throw new ApiError(404, 'Interno no encontrado');
}

// Traduce el error de legajo duplicado
// Ejecuta una escritura y traduce el error 1062 de MySQL (clave duplicada) a un 409 entendible
function guardar(callable $accion, string $campo = 'legajo', string $mensaje = 'Ya existe un interno con ese legajo'): void
{
    try {
        $accion();
    } catch (PDOException $errorBd) {
        if (($errorBd->errorInfo[1] ?? 0) === 1062) {
            throw new ApiError(409, $mensaje, [$campo => 'Valor duplicado']);
        }
        throw $errorBd;
    }
}

// Registra un alta o baja en el historial (recibe un interno o los datos ya validados)
function registrarHistorial(PDO $conexion, array $datosInterno, string $tipo, string $detalles): void
{
    $conexion->prepare('INSERT INTO historial (legajo, interno, tipo, fecha, detalles, usuario) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$datosInterno['legajo'], "{$datosInterno['apellido_paterno']} {$datosInterno['apellido_materno']}, {$datosInterno['nombres']}", $tipo,
            date('Y-m-d H:i:s'), mb_substr($detalles, 0, 200), $_SESSION['usuario']]);
}



// Agrega la actividad al registro en la base de datos
function registrarActividad(PDO $conexion, array $interno, string $accion, string $actividad, ?string $subactividad): void
{
    $consulta = $conexion->prepare(
        'INSERT INTO registro_actividades (interno_id, legajo, nombre, accion, actividad, subactividad, fecha, usuario) VALUES (:id, :leg, :nombre, :accion, :actividad, :subactividad, :fecha, :usuario)'
    );
    $consulta->execute([
        ":id" => $interno['id'],
        ":leg" => $interno['legajo'],
        ":nombre" => "{$interno['apellido_paterno']} {$interno['apellido_materno']}, {$interno['nombres']}",
        ":accion" => $accion,
        ":actividad" => $actividad,
        ":subactividad" => $subactividad,
        ":fecha" => date('Y-m-d H:i:s'),
        ":usuario" => $_SESSION['usuario']
    ]);
}

// Enrutador 
// Enrutador 
try {
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
    // LOGIN: valida usuario y contraseña y abre la sesion
    if ($recurso === 'login' && $metodo === 'POST') {
        $datosRecibidos = leerJson();
        // Conexion a MySQL
        $conexion = db();
        // Primer arranque: si no hay usuarios, crea 'admin' con la clave de ADMIN_PASSWORD (guardada como hash, nunca en texto plano)
        $cantidadUsuarios = (int)$conexion->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
        if ($cantidadUsuarios === 0) {
            $contrasenaAdmin = getenv('ADMIN_PASSWORD');
            if (!$contrasenaAdmin) {
                // Falla de configuracion del servidor: se registra en el log y se avisa con un 500
                error_log('Falta definir ADMIN_PASSWORD: no hay usuarios y no se puede crear el admin');
                throw new ApiError(500, 'Sistema sin configurar: falta definir ADMIN_PASSWORD en el servidor');
            }
            $conexion->prepare("INSERT INTO usuarios (usuario, hash, rol) VALUES ('admin', ?, 'admin')")
                ->execute([password_hash($contrasenaAdmin, PASSWORD_DEFAULT)]);
        }
        $consulta = $conexion->prepare('SELECT usuario, hash, rol FROM usuarios WHERE usuario = ?');
        $consulta->execute([txt($datosRecibidos['usuario'] ?? null)]);
        $usuarioEncontrado = $consulta->fetch();
        // password_verify compara contra el hash; el mismo mensaje si falla el usuario o la clave (no revela cual)
        if (!$usuarioEncontrado || !password_verify(txt($datosRecibidos['password'] ?? null), $usuarioEncontrado['hash'])) {
            throw new ApiError(401, 'Usuario o contraseña incorrectos');
        }
        // Nuevo id de sesion tras el login (evita fijacion de sesion)
        session_regenerate_id(true);
        // Guarda en la sesion quien es y su rol
        $_SESSION = ['usuario' => $usuarioEncontrado['usuario'], 'rol' => $usuarioEncontrado['rol']];
        responder(200, $_SESSION);
    }

    // LOGOUT: destruye la sesion
    if ($recurso === 'logout' && $metodo === 'POST') {
        $_SESSION = [];
        session_destroy();
        responder(200, ['ok' => true]);
    }

    // El frontend lo usa al abrir la pagina para saber si ya hay sesion
    if ($recurso === 'sesion' && $metodo === 'GET') {
        responder(200, ['usuario' => exigirLogin()['usuario'], 'rol' => $_SESSION['rol']]);
    }

    // Listas de pisos y actividades para armar los formularios
    if ($recurso === 'config' && $metodo === 'GET') {
        exigirLogin();
        responder(200, ['pisos' => PISOS, 'actividades' => ACTIVIDADES]);
    }

    // Servicios externos degradan a null si fallan
    if ($recurso === 'servicios' && $metodo === 'GET') {
        exigirLogin();
        $ubicacion = ubicacionEstablecimiento();
        // Si no hay ubicacion el clima es null; igual responde 200 (degradacion elegante)
        responder(200, ['ubicacion' => $ubicacion, 'clima' => $ubicacion ? climaActual($ubicacion['lat'], $ubicacion['lon']) : null]);
    }

    // Historial de altas y bajas (admin y encargado)
    if ($recurso === 'historial' && $metodo === 'GET') {
        exigirRol(['admin', 'encargado']);
        // LIKE con % filtra por parte del legajo; LIMIT 200 acota la respuesta
        $consulta = db()->prepare('SELECT id, legajo, interno, tipo, fecha, detalles, usuario FROM historial
            WHERE legajo LIKE ? ORDER BY id DESC LIMIT 200');
        $consulta->execute(['%' . txt($_GET['legajo'] ?? '') . '%']);
        responder(200, $consulta->fetchAll());
    }

    // Actualiza la lista de los registros segun el legajo ingresado en el input
    if ($recurso === 'registro-actividades' && $metodo === 'GET') {
        exigirRol(['admin', 'encargado']);
   
        $consulta = db()->prepare(
            'SELECT fecha, accion, actividad, subactividad, legajo, nombre, usuario
            FROM registro_actividades
            WHERE legajo LIKE ?
            ORDER BY id DESC 
            LIMIT 50');
        $consulta->execute(['%' . txt($_GET['legajo'] ?? '') . '%']);
        responder(200, $consulta->fetchAll());
    }

  
    

    // Usuarios (solo admin)
    if ($recurso === 'usuarios') {
        exigirRol(['admin']);
        // El id debe ser numerico; si no, 400 (peticion mal formada)
        if ($id !== null && !ctype_digit($id)) {
            throw new ApiError(400, 'El id debe ser numdrico');
        }
        if ($id === null && $metodo === 'GET') {                   // LISTAR
            responder(200, db()->query('SELECT id, usuario, rol FROM usuarios ORDER BY rol, usuario')->fetchAll());
        }
        if ($id === null && $metodo === 'POST') {                      // CREAR
            $datosRecibidos = leerJson();
            $nombreUsuarioNuevo = txt($datosRecibidos['usuario'] ?? null);
            $contrasenaNueva  = is_string($datosRecibidos['password'] ?? null) ? $datosRecibidos['password'] : '';
            $rol    = txt($datosRecibidos['rol'] ?? null);
            $errores = [];
            if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $nombreUsuarioNuevo)) {
                $errores['usuario'] = 'Usuario de 3 a 50 caracteres (letras, numeros, . _ -)';
            }
            if (mb_strlen($contrasenaNueva) < 6 || strlen($contrasenaNueva) > 72) {
                $errores['password'] = 'La contraseña debe tener entre 6 y 72 caracteres';
            }
            if (!in_array($rol, ['admin', 'encargado', 'guardia'], true)) {
                $errores['rol'] = 'Rol invalido';
            }
            if ($errores) {
                throw new ApiError(422, 'Datos invalidos', $errores);
            }
            guardar(fn() => db()->prepare('INSERT INTO usuarios (usuario, hash, rol) VALUES (?, ?, ?)')
                ->execute([$nombreUsuarioNuevo, password_hash($contrasenaNueva, PASSWORD_DEFAULT), $rol]), 'usuario', 'Ya existe un usuario con ese nombre');
            responder(201, ['id' => (int)db()->lastInsertId(), 'usuario' => $nombreUsuarioNuevo, 'rol' => $rol]);
        }

        if ($id !== null && $metodo === 'DELETE') {                    // BORRAR
            $consulta = db()->prepare('SELECT usuario FROM usuarios WHERE id = ?');
            $consulta->execute([(int)$id]);
            $nombreUsuarioABorrar = $consulta->fetchColumn();
            if ($nombreUsuarioABorrar === false) {
                throw new ApiError(404, 'Usuario no encontrado');
            }
            if ($nombreUsuarioABorrar === $_SESSION['usuario']) {
                throw new ApiError(409, 'No puede eliminarse a si mismo');
            }
            db()->prepare('DELETE FROM usuarios WHERE id = ?')->execute([(int)$id]);
            responder(200, ['ok' => true, 'mensaje' => 'Usuario eliminado']);
        }
    }

    // Internos
    if ($recurso === 'internos') {
        exigirLogin();
        if ($id !== null && !ctype_digit($id)) {
            throw new ApiError(400, 'El id debe ser numerico');
        }

        if ($id === null && $metodo === 'GET') {                       // LISTAR
            // Texto de busqueda con comodines % para LIKE (va como parametro preparado: sin riesgo de inyeccion SQL)
            $textoBusqueda = '%' . txt($_GET['q'] ?? '') . '%';
            $consulta = db()->prepare(SQL_INTERNOS . ' WHERE i.legajo LIKE ? OR i.apellido_paterno LIKE ?
                OR i.apellido_materno LIKE ? OR i.nombres LIKE ? ORDER BY i.piso, i.celda, i.apellido_paterno');
            $consulta->execute([$textoBusqueda, $textoBusqueda, $textoBusqueda, $textoBusqueda]);
            responder(200, $consulta->fetchAll());
        }

        if ($id === null && $metodo === 'POST') {                      // CREAR
            exigirRol(['admin', 'encargado']);
            // Valida el JSON recibido; si hay errores responde 422
            [$datosValidados, $errores] = validarInterno(leerJson());
            if ($errores) {
                throw new ApiError(422, 'Datos inválidos', $errores);
            }
            // Se completa dentro de la transaccion y se usa para devolver el interno creado
            $nuevoId = 0;
            guardar(function () use ($datosValidados, &$nuevoId) {
                $conexion = db();
                // Transaccion: el alta y su registro en el historial se guardan juntos o no se guarda nada
                $conexion->beginTransaction();                                // alta + historial: todo o nada
                try {
                    $conexion->prepare('INSERT INTO internos (legajo, apellido_paterno, apellido_materno, nombres, piso, celda)
                        VALUES (:legajo, :apellido_paterno, :apellido_materno, :nombres, :piso, :celda)')->execute($datosValidados);
                    $nuevoId = (int)$conexion->lastInsertId();
                    registrarHistorial($conexion, $datosValidados, 'alta', 'Ingreso');
                    $conexion->commit();
                } catch (Throwable $falla) {
                    // Ante cualquier error se deshace todo y se relanza la excepcion
                    $conexion->rollBack();
                    throw $falla;
                }
            });
            responder(201, buscarInterno($nuevoId));
        }

        if ($id !== null && $accion === null && $metodo === 'GET') {   // VER UN INTERNO
            responder(200, buscarInterno((int)$id));
        }

        if ($id !== null && $accion === null && $metodo === 'PUT') {   // MODIFICAR
            exigirRol(['admin', 'encargado']);
            buscarInterno((int)$id);                                   // 404 si no existe
            [$datosValidados, $errores] = validarInterno(leerJson());
            if ($errores) {
                throw new ApiError(422, 'Datos invalidos', $errores);
            }
            guardar(fn() => db()->prepare('UPDATE internos SET legajo=:legajo, apellido_paterno=:apellido_paterno,
                apellido_materno=:apellido_materno, nombres=:nombres, piso=:piso, celda=:celda WHERE id=:id')
                ->execute($datosValidados + ['id' => (int)$id]));
            responder(200, buscarInterno((int)$id));
        }

        if ($id !== null && $accion === null && $metodo === 'DELETE') { // BORRAR
            exigirRol(['admin', 'encargado']);
            // Primero se lee el interno: da el 404 si no existe y aporta los datos para el historial
            $interno = buscarInterno((int)$id);
            $conexion = db();
            $conexion->beginTransaction();                                    // historial + borrado: todo o nada
            try {
                registrarHistorial($conexion, $interno, 'baja', txt($_GET['motivo'] ?? '') ?: 'Baja');
                $conexion->prepare('DELETE FROM internos WHERE id = ?')->execute([(int)$id]);
                $conexion->commit();
            } catch (Throwable $falla) {
                $conexion->rollBack();
                throw $falla;
            }
            responder(200, ['ok' => true, 'mensaje' => 'Interno eliminado']);
        }

        // Movimientos del interno
        if ($id !== null && $accion === 'salida' && $metodo === 'POST') {
            $interno = buscarInterno((int)$id);
            $datosRecibidos = leerJson();
            $actividad = txt($datosRecibidos['actividad'] ?? null);
            $subactividad = txt($datosRecibidos['subactividad'] ?? null);
            if (!isset(ACTIVIDADES[$actividad]) || !in_array($subactividad, ACTIVIDADES[$actividad], true)) {
                throw new ApiError(422, 'Datos invalidos', ['actividad' => 'Actividad o destino invalido']);
            }
            // actividad no nula = ya tiene una salida sin regreso --> 409 (conflicto)
            if ($interno['actividad'] !== null) {
                throw new ApiError(409, 'El interno ya tiene una salida pendiente de regreso');
            }
            db()->prepare('INSERT INTO movimientos (interno_id, actividad, subactividad, salida, usuario) VALUES (?, ?, ?, ?, ?)')
                ->execute([(int)$id, $actividad, $subactividad, date('Y-m-d H:i:s'), $_SESSION['usuario']]);
            
            // registra la salida de la actividad
            $conexion = db();
            registrarActividad($conexion, $interno, 'salida', $actividad, $subactividad);
            responder(201, buscarInterno((int)$id));
        }

        if ($id !== null && $accion === 'regreso' && $metodo === 'POST') {
            // Obtener datos del movimiento actual para registrar en el registro de actividades
            $movimiento = db()->prepare("SELECT actividad, subactividad FROM movimientos WHERE interno_id = ? AND regreso IS NULL LIMIT 1");
            $movimiento->execute([(int)$id]);
            $datosMov = $movimiento->fetch();
            
            if (buscarInterno((int)$id)['actividad'] === null) {
                throw new ApiError(409, 'No hay salida pendiente');
            }
            // Cierra la salida pendiente poniendole la hora de regreso
            db()->prepare('UPDATE movimientos SET regreso = ? WHERE interno_id = ? AND regreso IS NULL')
                ->execute([date('Y-m-d H:i:s'), (int)$id]);

            // Registrar en el regreso de la actividad
            $interno = buscarInterno((int)$id);
            registrarActividad(db(), $interno, 'regreso', $datosMov['actividad'] ?? '', $datosMov['subactividad'] ?? '');
            responder(200, $interno);
        }
    }

    // Ninguna ruta coincidio --> 404
    throw new ApiError(404, 'Ruta no encontrada');

// Errores esperados: se responden con su propio codigo (400, 401, 403, 404, 409, 422)
} catch (ApiError $errorApi) {
    responder($errorApi->codigoHttp, ['error' => $errorApi->getMessage()] + ($errorApi->detalles ? ['detalles' => $errorApi->detalles] : []));
// Cualquier otro fallo: 500 generico; el detalle va al log del servidor, nunca al usuario
} catch (Throwable $falla) {
    error_log($falla);                                                   // el detalle va al log, no al cliente
    responder(500, ['error' => 'Error interno del servidor']);
}


