<?php
declare(strict_types=1);

// Registra un alta o baja en el historial (recibe un interno o los datos ya validados)
function registrarHistorial(PDO $conexion, array $datosInterno, string $tipo, string $detalles): void
{
    $consulta = $conexion->prepare(
        'INSERT INTO historial (legajo, interno, tipo, fecha, detalles, usuario)
        VALUES (:legajo, :interno, :tipo, :fecha, :detalles, :usuario)');
    $consulta->execute([
        ':legajo' => $datosInterno['legajo'],
        ':interno' => "{$datosInterno['apellido_paterno']} {$datosInterno['apellido_materno']}, {$datosInterno['nombres']}",
        ':tipo' => $tipo,
        ':fecha' => date('Y-m-d H:i:s'),
        ':detalles' => mb_substr($detalles, 0, 200),
        ':usuario' => $_SESSION['usuario']
    ]);
}

// Historial de altas y bajas (admin y encargado)
function manejarHistorial(): void
{
    exigirRol(['admin', 'encargado']);
    // LIKE con % filtra por parte del legajo; LIMIT 200 acota la respuesta
    $consulta = db()->prepare('SELECT id, legajo, interno, tipo, fecha, detalles, usuario FROM historial
        WHERE legajo LIKE :legajo ORDER BY id DESC LIMIT 200');
    $consulta->execute([':legajo' => '%' . txt($_GET['legajo'] ?? '') . '%']);
    responder(200, $consulta->fetchAll());
}
