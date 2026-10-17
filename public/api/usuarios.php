<?php
declare(strict_types=1);

function manejarUsuarios(string $metodo, ?string $id): void
{
    exigirRol(['admin']);

    // El id debe ser numerico, sino tira error 400
    if ($id !== null && !ctype_digit($id)) {
        throw new ApiError(400, 'El id debe ser numerico');
    }

    // Lista los usuarios
    if ($id === null && $metodo === 'GET') {
        responder(200, db()->query('SELECT id, usuario, rol FROM usuarios ORDER BY rol, usuario')->fetchAll());
    }

    // Crea un usuario nuevo
    if ($id === null && $metodo === 'POST') {
        $datosRecibidos = leerJson();
        $nombreUsuarioNuevo = txt($datosRecibidos['usuario'] ?? null);
        $contrasenaNueva = is_string($datosRecibidos['password'] ?? null) ? $datosRecibidos['password'] : '';
        $rol = txt($datosRecibidos['rol'] ?? null);
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

        guardar(
            fn() => db()->prepare('INSERT INTO usuarios (usuario, hash, rol) VALUES (:usuario, :hash, :rol)')
                ->execute([
                    ':usuario' => $nombreUsuarioNuevo,
                    ':hash'    => password_hash($contrasenaNueva, PASSWORD_DEFAULT),
                    ':rol'     => $rol,
                ]),
            'usuario',
            'Ya existe un usuario con ese nombre'
        );
        responder(201, ['id' => (int)db()->lastInsertId(), 'usuario' => $nombreUsuarioNuevo, 'rol' => $rol]);
    }

    // Borra un usuario 
    if ($id !== null && $metodo === 'DELETE') {
        $consulta = db()->prepare('SELECT usuario FROM usuarios WHERE id = :id');
        $consulta->execute([':id' => (int)$id]);
        $nombreUsuarioABorrar = $consulta->fetchColumn();
        if ($nombreUsuarioABorrar === false) {
            throw new ApiError(404, 'Usuario no encontrado');
        }
        if ($nombreUsuarioABorrar === $_SESSION['usuario']) {
            throw new ApiError(409, 'No puede eliminarse a si mismo');
        }

        db()->prepare('DELETE FROM usuarios WHERE id = :id')
            ->execute([':id' => (int)$id]);
        responder(200, ['ok' => true, 'mensaje' => 'Usuario eliminado']);
    }
}
