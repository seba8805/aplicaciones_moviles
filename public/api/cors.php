<?php
declare(strict_types=1);

// configuracion de CORS para la API
function aplicarCors(): void
{
    // Lista de origines permitidos para usar la API
    $origenesStr = getenv('ORIGENES_PERMITIDOS');
    if ($origenesStr !== false && $origenesStr !== '') {
        $origenesLista = explode(',', $origenesStr);
        $origenesPermitidos = [];
        foreach ($origenesLista as $origen) {
            if (trim($origen) !== '') {
                $origenesPermitidos[] = trim($origen);
            }
        }
    } else {
        $origenesPermitidos = [
        'http://localhost',
        'https://localhost',
        'http://127.0.0.1',
        'https://127.0.0.1',
        'http://localhost:8000',
        'https://localhost:8000',
        'http://127.0.0.1:8000',
        'https://127.0.0.1:8000',
        ];
    }

    // Obtiene el origen
    $origen = $_SERVER['HTTP_ORIGIN'] ?? '';

    // Si el origen es uno de los permitidos se permite el acceso, permite credenciales
    if ($origen !== '' && in_array($origen, $origenesPermitidos, true)) {
        header("Access-Control-Allow-Origin: $origen");
        header("Vary: Origin");
        header("Access-Control-Allow-Credentials: true");
    }

    // Metodos que acepta la API
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    // cache de acceso por 24 horas
    header("Access-Control-Max-Age: 86400");

    // si el metodo es OPTIONS responde 200 pero termina la ejecucion
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}
