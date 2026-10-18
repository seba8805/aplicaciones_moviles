<?php
declare(strict_types=1);

// Valida que una variable de entorno requerida esté presente.
// Devuelve el valor o lanza un ApiError(500) si falta.
function exigirEnv(string $nombre): string
{
    $valor = getenv($nombre);
    if ($valor === false || $valor === '') {
        error_log("Falta variable de entorno: $nombre");
        throw new ApiError(500, "Sistema sin configurar: falta definir $nombre en el servidor");
    }
    return $valor;
}

// Auto-inicialización de la base de datos.
// Si no existen las tablas, ejecuta sql/database.sql (útil para deploys en la nube como Railway).
function asegurarTablas(): void
{
    $conexion = db();

    $existe = $conexion->query("SHOW TABLES LIKE 'usuarios'")->fetchColumn();
    if ($existe) {
        return; // Las tablas ya están creadas
    }

    $sql = file_get_contents(__DIR__ . '/../../sql/database.sql');
    if ($sql === false) {
        throw new ApiError(500, 'No se pudo leer el archivo database.sql');
    }

    // Separa por punto y coma y ejecuta cada statement limpiando comentarios
    $statementes = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => $s !== ''
    );

    foreach ($statementes as $stmt) {
        // Elimina comentarios de línea que empiezan con --
        $stmt = preg_replace('/--.*$/m', '', $stmt);
        // Elimina bloques de comentario /* ... */ simples de una línea
        $stmt = preg_replace('/\/\*[^*]*\*+\//', '', $stmt);
        $stmt = trim($stmt);
        if ($stmt !== '') {
            $conexion->exec($stmt);
        }
    }
}