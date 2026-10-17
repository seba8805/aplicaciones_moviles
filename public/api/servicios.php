<?php
declare(strict_types=1);

// SERVICIOS EXTERNOS (se llaman desde el backend)
// Nominatim / OpenStreetMap -> geocodificacion de la dirección
// Open-Meteo                -> clima actual
// Ninguno requiere clave. Si alguno falla devuelven null y la app sigue funcionando.

// GET a una API JSON con timeout corto; null ante cualquier falla
function httpJson(string $url): ?array
{
    // El cliente HTTP de PHP, con el que se llama a los servicios externos
    $conexionCurl = curl_init($url);
    curl_setopt_array($conexionCurl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,                       // no colgar la app por un servicio lento
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_USERAGENT      => 'ControlInternos/1.0 (proyecto academico)', // Nominatim lo exige
    ]);
    $cuerpoRespuesta = curl_exec($conexionCurl);
    $codigoHttp = curl_getinfo($conexionCurl, CURLINFO_HTTP_CODE);
    curl_close($conexionCurl);
    // Falla de red o respuesta distinta de 200 --> null: el error no se propaga a la app
    if ($cuerpoRespuesta === false || $codigoHttp !== 200) {
        return null;
    }
    $datosJson = json_decode($cuerpoRespuesta, true);
    return is_array($datosJson) ? $datosJson : null;
}

// Cache en archivo temporal para no abusar de los servicios gratuitos
function conCache(string $claveCache, int $segundosValidez, callable $funcionObtener): ?array
{
    // Un archivo por clave (hash md5) en la carpeta temporal del servidor
    $archivoCache = sys_get_temp_dir() . '/ci_' . md5($claveCache) . '.json';
    // Si el archivo es reciente, se devuelve sin llamar al servicio externo
    if (is_file($archivoCache) && time() - filemtime($archivoCache) < $segundosValidez) {
        return json_decode((string)file_get_contents($archivoCache), true);
    }
    // Cache vencida o inexistente: se llama al servicio real
    $valor = $funcionObtener();
    // Solo se guarda si hubo respuesta valida (las fallas no se cachean)
    if ($valor !== null) {
        @file_put_contents($archivoCache, json_encode($valor));
    }
    return $valor;
}

// Coordenadas del establecimiento: geocodifica la direccion; si falla, usa LAT/LON del entorno
function ubicacionEstablecimiento(): ?array
{
    $nombreEstablecimiento    = getenv('ESTABLECIMIENTO_NOMBRE') ?: 'Establecimiento';
    $direccion = getenv('ESTABLECIMIENTO_DIRECCION');
    if ($direccion) {
        // Geocodificacion: direccion --> latitud y longitud
        $coordenadas = conCache('geo:' . $direccion, 86400, function () use ($direccion) {
            $respuesta = httpJson('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($direccion));
            return isset($respuesta[0]['lat'], $respuesta[0]['lon']) ? ['lat' => (float)$respuesta[0]['lat'], 'lon' => (float)$respuesta[0]['lon']] : null;
        });
        if ($coordenadas) {
            return $coordenadas + ['nombre' => $nombreEstablecimiento];
        }
    }
    $latitud = getenv('ESTABLECIMIENTO_LAT');
    $longitud = getenv('ESTABLECIMIENTO_LON');
    // Opcion B: si el servicio fallo, usa las coordenadas fijas del entorno (o null)
    return (is_numeric($latitud) && is_numeric($longitud)) ? ['lat' => (float)$latitud, 'lon' => (float)$longitud, 'nombre' => $nombreEstablecimiento] : null;
}

// Clima actual en unas coordenadas (Open-Meteo)
function climaActual(float $latitud, float $longitud): ?array
{
    // El clima se cachea 10 minutos
    return conCache("clima:$latitud,$longitud", 600, function () use ($latitud, $longitud) {
        $respuesta = httpJson("https://api.open-meteo.com/v1/forecast?latitude=$latitud&longitude=$longitud&current=temperature_2m,wind_speed_10m,weather_code");
        $datosActuales = $respuesta['current'] ?? null;
        // Devuelve solo los datos que usa el frontend, o null si no hubo respuesta
        return $datosActuales ? ['temperatura' => $datosActuales['temperature_2m'], 'viento' => $datosActuales['wind_speed_10m'], 'codigo' => $datosActuales['weather_code']] : null;
    });
}

// Servicios externos degradan a null si fallan
function manejarServicios(): void
{
    exigirLogin();
    $ubicacion = ubicacionEstablecimiento();
    // Si no hay ubicacion el clima es null; igual responde 200 (degradacion elegante)
    responder(200, ['ubicacion' => $ubicacion, 'clima' => $ubicacion ? climaActual($ubicacion['lat'], $ubicacion['lon']) : null]);
}
