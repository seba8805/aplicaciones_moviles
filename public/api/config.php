<?php
// Fuerza el tipado estricto para evitar conversiones implicitas de tipos.
declare(strict_types=1);

ini_set('display_errors', '0');          // produccion: nunca mostrar errores al usuario
ini_set('log_errors', '1');              // se registran en el log del servidor
error_reporting(E_ALL);
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Constantes 
// Listas fijas de la app: el servidor las usa para validar y el frontend para armar los formularios
const PISOS = ['Planta Baja', 'Primer Piso', 'Segundo Piso', 'Pieza Exterior'];

// actividad con sus subactividades permitidas
const ACTIVIDADES = [                    
    'Estudiar'      => ['Biblioteca', 'Primaria', 'Secundaria', 'Universidad'],
    'Trabajo'       => ['Carpinteria', 'Electricidad', 'Cocina', 'Zoom'],
    'Sanidad'       => ['Dentista', 'Medico', 'Psicologo'],
    'Visita'        => ['Familiar', 'Intima', 'Asistencia Legal'],
    'Cursos Varios' => ['Carpinteria', 'Artesania', 'Computacion', 'Peluqueria', 'Panaderia', 'Electricidad', 'Seguridad e Higiene'],
    'Traslado'      => ['Hospital', 'Juzgado'],
];

// Listas de pisos y actividades para armar los formularios
function manejarConfig(): void
{
    exigirLogin();
    responder(200, ['pisos' => PISOS, 'actividades' => ACTIVIDADES]);
}

// Carga las variables desde el archivo .env 
function cargarEnv(string $ruta): void
{
    // en caso que no exista el archivo
    if (!is_file($ruta)) {
        return;                                              
    }

    
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {

        $linea = trim($linea);

        // ignora lineas vacias, comentarios y lineas sin un =
        if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
            continue;                                        
        }

        [$nombre, $valor] = explode('=', $linea, 2);
        $nombre = trim($nombre);
        $valor  = trim($valor);
        // quita las comillas
        if (strlen($valor) >= 2 && in_array($valor[0], ['"', "'"], true) && $valor[-1] === $valor[0]) {
            $valor = substr($valor, 1, -1);                  
        }

        // si no existe la variable la agrega, sino la saltea
        if (getenv($nombre) === false) {                     
            putenv("$nombre=$valor");
        }
    }
}
// .env en la carpeta raíz del proyecto
cargarEnv(__DIR__ . '/../../.env');
