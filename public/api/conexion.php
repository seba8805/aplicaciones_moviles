<?php
declare(strict_types=1);

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
