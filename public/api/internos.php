<?php
declare(strict_types=1);

// Valida los datos de un interno, los returna junto con los errores que hayan surgido
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

// Query base que consulta los internos y su ubicacion
const SQL_INTERNOS = 
        "SELECT i.id, i.legajo, i.apellido_paterno, i.apellido_materno, i.nombres, i.piso, i.celda,
        m.actividad, m.subactividad, m.salida,
        CASE WHEN m.id IS NULL THEN 'En celda'
        WHEN m.actividad = 'Traslado' THEN CONCAT('En Tránsito ', m.subactividad)
        ELSE m.subactividad END AS ubicacion
        FROM internos i LEFT JOIN movimientos m ON m.interno_id = i.id AND m.regreso IS NULL";

// Busca un interno por id; si no existe lanza el 404
function buscarInterno(int $id): array
{
    # Se ejecuta el query con el id recibiido
    $consulta = db()->prepare(SQL_INTERNOS . ' WHERE i.id = :id');
    $consulta->execute([':id' => $id]);
    // fetch() devuelve false si no hay fila, se lanza ApiError 404
    return $consulta->fetch() ?: throw new ApiError(404, 'Interno no encontrado');
}

// Intenta hacer una escritura, en caso de haber un error 1062 de MySQL (clave duplicada) lo traduce  a un 409 entendible
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

function manejarInternos(string $metodo, ?string $id, ?string $accion): void
{
    exigirLogin();
    if ($id !== null && !ctype_digit($id)) {
        throw new ApiError(400, 'El id debe ser numerico');
    }

    // Lista los internos
    if ($id === null && $metodo === 'GET') {
        $textoBusqueda = '%' . txt($_GET['q'] ?? '') . '%';
        $consulta = db()->prepare(SQL_INTERNOS . ' WHERE i.legajo LIKE ? OR i.apellido_paterno LIKE ?
            OR i.apellido_materno LIKE ? OR i.nombres LIKE ? ORDER BY i.piso, i.celda, i.apellido_paterno');
        $consulta->execute([$textoBusqueda, $textoBusqueda, $textoBusqueda, $textoBusqueda]);
        responder(200, $consulta->fetchAll());
    }

    // Crea un interno nuevo
    if ($id === null && $metodo === 'POST') {
        exigirRol(['admin', 'encargado']);

        // valida el json
        [$datosValidados, $errores] = validarInterno(leerJson());
        
        // tira error en caso qeu el json este mal
        if ($errores) {
            throw new ApiError(422, 'Datos inválidos', $errores);
        }

        // se guarda al interno y se lo registra en el historial  a traves de una transaccion
        $nuevoId = 0;
        guardar(function () use ($datosValidados, &$nuevoId) {
            $conexion = db();
            $conexion->beginTransaction();

            // en caso que falle la transaccion se hace el rollback y se lanza el error
            try {
                $conexion->prepare('INSERT INTO internos (legajo, apellido_paterno, apellido_materno, nombres, piso, celda)
                    VALUES (:legajo, :apellido_paterno, :apellido_materno, :nombres, :piso, :celda)')->execute($datosValidados);
                $nuevoId = (int)$conexion->lastInsertId();
                registrarHistorial($conexion, $datosValidados, 'alta', 'Ingreso');
                $conexion->commit();
            } catch (Throwable $falla) {
                $conexion->rollBack();
                throw $falla;
            }
        });
        responder(201, buscarInterno($nuevoId));
    }

    // Lista un interno en especifico por ID
    if ($id !== null && $accion === null && $metodo === 'GET') {
        responder(200, buscarInterno((int)$id));
    }

    // Modifica un interno por ID
    if ($id !== null && $accion === null && $metodo === 'PUT') {
        exigirRol(['admin', 'encargado']);
        buscarInterno((int)$id);
        [$datosValidados, $errores] = validarInterno(leerJson());
        if ($errores) {
            throw new ApiError(422, 'Datos invalidos', $errores);
        }
        guardar(fn() => db()->prepare('UPDATE internos SET legajo=:legajo, apellido_paterno=:apellido_paterno,
            apellido_materno=:apellido_materno, nombres=:nombres, piso=:piso, celda=:celda WHERE id=:id')
            ->execute($datosValidados + ['id' => (int)$id]));
        responder(200, buscarInterno((int)$id));
    }

    // Borra un interno por ID
    if ($id !== null && $accion === null && $metodo === 'DELETE') {
        exigirRol(['admin', 'encargado']);
        $interno = buscarInterno((int)$id);
        $conexion = db();
        $conexion->beginTransaction();
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

    // Registra la salida a una actividad de un interno
    if ($id !== null && $accion === 'salida' && $metodo === 'POST') {
        $interno = buscarInterno((int)$id);
        $datosRecibidos = leerJson();
        $actividad = txt($datosRecibidos['actividad'] ?? null);
        $subactividad = txt($datosRecibidos['subactividad'] ?? null);
        if (!isset(ACTIVIDADES[$actividad]) || !in_array($subactividad, ACTIVIDADES[$actividad], true)) {
            throw new ApiError(422, 'Datos invalidos', ['actividad' => 'Actividad o destino invalido']);
        }
        if ($interno['actividad'] !== null) {
            throw new ApiError(409, 'El interno ya tiene una salida pendiente de regreso');
        }
        db()->prepare('INSERT INTO movimientos (interno_id, actividad, subactividad, salida, usuario) VALUES (?, ?, ?, ?, ?)')
            ->execute([(int)$id, $actividad, $subactividad, date('Y-m-d H:i:s'), $_SESSION['usuario']]);

        // registra la actividad en la base de datos
        registrarActividad(db(), $interno, 'salida', $actividad, $subactividad);
        responder(201, buscarInterno((int)$id));
    }

    // Registra el regreso de una actividad de un interno
    if ($id !== null && $accion === 'regreso' && $metodo === 'POST') {
        $movimiento = db()->prepare('SELECT actividad, subactividad FROM movimientos WHERE interno_id = ? AND regreso IS NULL LIMIT 1');
        $movimiento->execute([(int)$id]);
        $datosMov = $movimiento->fetch();

        if (buscarInterno((int)$id)['actividad'] === null) {
            throw new ApiError(409, 'No hay salida pendiente');
        }

        // Cierra la salida poniendo la hora de regreso
        db()->prepare('UPDATE movimientos SET regreso = ? WHERE interno_id = ? AND regreso IS NULL')
            ->execute([date('Y-m-d H:i:s'), (int)$id]);

        // Registro el regreso en la base de datos
        $interno = buscarInterno((int)$id);
        registrarActividad(db(), $interno, 'regreso', $datosMov['actividad'] ?? '', $datosMov['subactividad'] ?? '');
        responder(200, $interno);
    }
}