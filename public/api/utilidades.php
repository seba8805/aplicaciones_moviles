<?php
declare(strict_types=1);

// Saca los espacios vacios de un string, en caso de no ser un string devuelve un string vacio
function txt(mixed $valor): string
{
    return is_string($valor) ? trim($valor) : '';
}

// Normaliza un texxto, capitalizando las primeras letras de cada palabra
function nombrePropio(string $texto): string
{
    return mb_convert_case(mb_strtolower($texto), MB_CASE_TITLE);
}


// Envia una respuesta JSON y termina
function responder(int $codigoHttp, array $datos): never
{
    // Fija el codigo HTTP de la respuesta (200, 201, 404...)
    http_response_code($codigoHttp);
    // Avisa al navegador que el cuerpo es JSON
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    // Convierte el array a JSON; JSON_UNESCAPED_UNICODE conserva tildes y ñ
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    // Corta la ejecucion: despues de responder no se hace nada mas
    exit;
}

// Lee el cuerpo JSON, retorna los datos o tira error codigo 400 en caso de no ser valido
function leerJson(): array
{
    $cuerpoCrudo = file_get_contents('php://input');
    // Convierte el JSON recibido en array
    $datos = json_decode($cuerpoCrudo ?: '', true);

    // si no es un array lanza el error
    if (!is_array($datos)) {
        throw new ApiError(400, 'El cuerpo debe ser un JSON valido');
    }
    return $datos;
}
