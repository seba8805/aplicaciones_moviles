<?php
declare(strict_types=1);

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

// LOGIN: valida usuario y contraseña y abre la sesion
function manejarLogin(): void
{
    $datosRecibidos = leerJson();
    // Conexion a MySQL
    $conexion = db();
    // Primer arranque: si no hay usuarios, crea 'admin' con la clave de ADMIN_PASSWORD (guardada como hash, nunca en texto plano)
    $cantidadUsuarios = (int)$conexion->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if ($cantidadUsuarios === 0) {
        $contrasenaAdmin = getenv('ADMIN_PASSWORD');
        if (!$contrasenaAdmin) {
            // Falla de configuracion del servidor: se avisa con un 500 y el router la registra.
            throw new ApiError(500, 'Sistema sin configurar: falta definir ADMIN_PASSWORD en el servidor');
        }
        $crearAdmin = $conexion->prepare("INSERT INTO usuarios (usuario, hash, rol) VALUES ('admin', :hash, 'admin')");
        $crearAdmin->execute([':hash' => password_hash($contrasenaAdmin, PASSWORD_DEFAULT)]);
    }
    $consulta = $conexion->prepare('SELECT usuario, hash, rol FROM usuarios WHERE usuario = :usuario');
    $consulta->execute([':usuario' => txt($datosRecibidos['usuario'] ?? null)]);
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
function manejarLogout(): void
{
    $_SESSION = [];
    session_destroy();
    responder(200, ['ok' => true]);
}

// El frontend lo usa al abrir la pagina para saber si ya hay sesion
function manejarSesion(): void
{
    responder(200, ['usuario' => exigirLogin()['usuario'], 'rol' => $_SESSION['rol']]);
}
