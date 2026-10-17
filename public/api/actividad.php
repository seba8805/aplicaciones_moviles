<?php
declare(strict_types=1);

// Agrega la actividad al registro de movimientos en la base de datos
function registrarActividad(PDO $conexion, array $interno, string $accion, string $actividad, ?string $subactividad): void
{
    $consulta = $conexion->prepare(
        'INSERT INTO registro_actividades (interno_id, legajo, nombre, accion, actividad, subactividad, fecha, usuario) 
        VALUES (:id, :leg, :nombre, :accion, :actividad, :subactividad, :fecha, :usuario)'
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


// Actualiza la lista de los registros segun el legajo ingresado en el input
function manejarRegistroActividades(): void
{
    exigirRol(['admin', 'encargado']);

    $consulta = db()->prepare(
        'SELECT fecha, accion, actividad, subactividad, legajo, nombre, usuario
        FROM registro_actividades
        WHERE legajo LIKE :legajo
        ORDER BY id DESC 
        LIMIT 50');
    $consulta->execute([':legajo' => '%' . txt($_GET['legajo'] ?? '') . '%']);
    responder(200, $consulta->fetchAll());
}