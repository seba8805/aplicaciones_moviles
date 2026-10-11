<?php
// Solo para pruebas locales
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($ruta, '/api/')) {
    require __DIR__ . '/public/api/index.php';
    return true;
}
return false;  