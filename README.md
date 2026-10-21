# Control de Movimientos de Internos (SPB)

Sistema web para registrar y consultar los movimientos de internos de un establecimiento: altas y bajas, salidas a actividades, regresos y ubicacion actual de cada interno. Este README describe el backend (API REST en PHP con MySQL) y el frontend (aplicacion de una sola pagina en JavaScript puro).

**Integrantes:**
- Sebastian Kamlosfsky
- Joaquin Enriques
- Xavi Juan
- Alexis Allende

**URL pública:** https://aplicacionesmoviles-production.up.railway.app/

## Como correrla en local con XAMPP

1. Abrir XAMPP y activar Apache y MySQL.
2. Copiar el contenido del proyecto dentro de `htdocs`.
3. Entrar en `http://localhost/phpmyadmin` y crear una base de datos vacía en MySQL  .
4. Crear un archivo `.env` en la raíz del proyecto con al menos estas variables:

```
DB_HOST= nombre del host de la base de datos, por defecto: localhost
DB_PORT= El puerto que usa MySQL en el XAMPP
DB_NAME= El nombre que elegiste al crear la BD
DB_USER= nombre del usuario admin de la base de datos, por defecto: root
DB_PASS=
DB_SSL=0
ADMIN_PASSWORD= (A eleccion del usuario)

```

Variables opcionales:
```
ESTABLECIMIENTO_NOMBRE=nombre del establecimiento, ej: Unidad Penitenciaria
ESTABLECIMIENTO_DIRECCION=Nombre del lugar para Geolocalizacion y clima, ej: General La Madrid, Buenos Aires, Argentina
ORIGENES_PERMITIDOS= (CORS, en caso de estar vacio es localhost)
```

5. Entrar a `http://localhost` y elegir la carpeta en la que se guardo el proyecto
6. Entrar a la carpeta public. ej: `https://localhost/aplicaciones_moviles/public/`
7. Entrar con el usuario admin y DB_PASS como contraseña.

La primera vez que se ejecute la aplicacion, `init_db.php` revisa si existe la tabla `usuarios`; si no existe, ejecuta `sql/database.sql` automáticamente para crear todas las tablas. No hace falta importar `database.sql` a mano.

Como deberia quedar la arquitectura del proyecto dentro de `htdocs`:

```
\htdocs\aplicaciones_moviles\
  public/
    index.html
    style.css
    app.js
    utilidades.js
    api/
      index.php
      config.php
      login.php
      internos.php
      usuarios.php
      historial.php
      actividad.php
      servicios.php
      conexion.php
      cors.php
      errores.php
      utilidades.php
      init_db.php
      .htaccess
    vistas/
      login.js
      main.js
      recuento.js
      historial.js
      registro.js
      usuarios.js
      nav.js
      tabla.js
    servicios/
      clima.js
  sql/
    database.sql
  router.php
  .env
  Dockerfile
  docker-entrypoint.sh
  .gitignore
  .dockerignore
```


### Endpoints

| Metodo | Ruta | Rol | Descripcion |
|---|---|---|---|
| POST | `/login` | Publico | Inicia sesion |
| POST | `/logout` | Sesion | Cierra sesion |
| GET | `/sesion` | Sesion | Devuelve usuario y rol actuales |
| GET | `/config` | Sesion | Pisos y actividades para armar formularios |
| GET | `/servicios` | Sesion | Ubicacion del establecimiento y clima actual |
| GET | `/internos?q=` | Sesion | Lista y busca internos por legajo, apellidos o nombres |
| POST | `/internos` | admin, encargado | Crea un interno |
| GET | `/internos/{id}` | Sesion | Consulta un interno |
| PUT | `/internos/{id}` | admin, encargado | Modifica un interno |
| DELETE | `/internos/{id}?motivo=` | admin, encargado | Da de baja un interno |
| POST | `/internos/{id}/salida` | Sesion | Registra la salida a una actividad |
| POST | `/internos/{id}/regreso` | Sesion | Registra el regreso |
| GET | `/historial?legajo=` | admin, encargado | Historial de altas y bajas (maximo 200) |
| GET | `/registro-actividades?legajo=` | admin, encargado | Registro de salidas y regresos (maximo 50) |
| GET | `/usuarios` | admin | Lista usuarios |
| POST | `/usuarios` | admin | Crea un usuario |
| DELETE | `/usuarios/{id}` | admin | Elimina un usuario (no puede eliminarse a si mismo) |


## Variables de entorno

| Variable | Obligatoria | Descripcion |
|---|---|---|
| `DB_HOST` | Si | Host de MySQL |
| `DB_NAME` | Si | Nombre de la base |
| `DB_USER` | Si | Usuario de la base |
| `DB_PASS` | No | Contrasena de la base |
| `DB_PORT` | No | Puerto (por defecto 3306) |
| `DB_SSL` | No | `1` para usar TLS con la base |
| `ADMIN_PASSWORD` | Si, en el primer login | Clave del usuario `admin` inicial |
| `ORIGENES_PERMITIDOS` | No | Origenes CORS separados por coma (por defecto, localhost) |
| `ESTABLECIMIENTO_NOMBRE` | No | Nombre del establecimiento |
| `ESTABLECIMIENTO_DIRECCION` | No | Direccion a geocodificar |
| `ESTABLECIMIENTO_LAT` / `ESTABLECIMIENTO_LON` | No | Coordenadas de respaldo si falla la geocodificacion |

## Servicios externos

Se llaman desde el backend (`servicios.php`):

- **Nominatim (OpenStreetMap):** se llama desde el backend para convertir la direccion del establecimiento en coordenadas. Se cachea 24 horas.
- **Open-Meteo:** se llama desde el backend para obtener el clima actual (temperatura, viento y codigo de clima). Se cachea 10 minutos.

Se llaman desde el frontend (`servicios/clima.js`):
- **Leaflet y OpenStreetMap:** se cargan desde el navegador (CDN) para mostrar el mapa con la ubicacion del establecimiento.


Si un servicio falla, la funcion devuelve `null` y la app sigue funcionando. Si falla la geocodificacion se usan `ESTABLECIMIENTO_LAT` y `ESTABLECIMIENTO_LON`. El cache se guarda en archivos temporales del servidor.


## Despliegue

**Proveedor elegido:** Railway (modelo **PaaS** — Platform as a Service).

**Por que Railway:** permite desplegar aplicaciones fullstack con contenedores Docker, maneja las variables de entorno desde su panel web y ofrece HTTPS por defecto sin configuracion adicional. Esto evita problemas con hostings compartidos gratuitos que bloquean peticiones que no vienen de un navegador (importante para la app Android del TP N.º 2).

**Pasos para volver a desplegar:**

1. Loguearse en Railway y entrar al dashboard.
2. Crear un nuevo proyecto, elegir "GitHub" y esperar a que se construya el proyecto.
3. Una vez construido, entrar al portal del proyecto, ir a Settings -> Networking -> Generate Domain. Con esto el frontend ya esta desplegado y tenemos la URL publica.
4. Crear las variables de entorno en la pestaña Variables (se puede pegar en el RAW Editor para ir mas rapido):
5. 
DB_HOST=""
DB_PORT=""
DB_NAME=""
DB_USER=""
DB_PASS=""
DB_SSL="0"
ADMIN_PASSWORD=(A eleccion del usuario)
ESTABLECIMIENTO_NOMBRE= (A eleccion del usuario)
ESTABLECIMIENTO_DIRECCION= (A eleccion del usuario)
ORIGENES_PERMITIDOS=""

6. Agregar la URL de la app generada a ORIGENES_PERMITIDOS. Se pueden agregar mas origenes separandolos por comas.
7. En el dashboard del proyecto de Railway: Add -> Database -> MySQL.
8. En el portal de la base de datos, ir a la pestaña Variables y copiar las siguientes variabels de entorno en el portal del proyecto: 
   - MYSQLHOST -> en la variable DB_HOST del proyecto
   - MYSQLPORT -> en la variable DB_PORT del proyecto
   - MYSQLDATABASE -> en la variable DB_NAME del proyecto
   - MYSQLPASSWORD -> en la variable `DB_PASS`del proyecto
9. En la parte superior del dashboard aparecera un pop-up que dice "Apply X Changes", darle a Deploy. Una vez que termina de cargar, la app esta desplegada y lista para usar.

La base de datos tambien esta en Railway (MySQL). Las tablas se crean solas en el primer pedido mediante `init_db.php` como cuando se usa localmente.


## Limitaciones conocidas

- Al eliminar un interno, sus registros en `registro_actividades` se eliminan junto con el interno, por lo que se pierde el historial de salidas y regresos de ese interno. El grupo no está decidido si dejarlo así o agregar un booleano `activo` al schema de internos para marcarlo como inactivo en lugar de borrarlo.
- Un usuario tipo `admin` puede eliminar a cualquier otro usuario `admin`, pero no a sí mismo. El grupo no está decidido si dejarlo así o permitir que un admin se elimine a sí mismo pero no a otros admins.


## Uso

1. Iniciar sesion con el usuario `admin` y la clave definida en `ADMIN_PASSWORD`. El usuario `admin` se crea en el primer login.
2. Desde la pantalla **Usuarios** (solo admin) crear los usuarios `encargado` y `guardia`.
3. Desde **Internos** dar de alta internos y registrar salidas y regresos de actividades.
4. **Recuento** muestra cuantos internos hay en celda y en actividad por piso.
5. **Historial de altas** y **Registro de Actividades** permiten auditar lo ocurrido, filtrando por legajo.

El usuario `admin` se crea en el primer login usando la ENV `ADMIN_PASSWORD`, guardado como hash.

## Como funciona la API

Todas las rutas empiezan con `/api`. `index.php` quita el prefijo, divide la ruta en `recurso/id/accion` y llama al manejador. Las respuestas son JSON.

## Frontend

Aplicacion de una sola pagina hecha con modulos de JavaScript. `app.js` es el centro: guarda el estado (`state`: contenedor, configuracion y usuario), define `api()` para llamar al backend y arranca la app.

### Funcionamiento general

- `api(ruta, opciones)` hace el `fetch` a `api/...` en formato JSON. Si la respuesta no es exitosa lanza un `Error` con el mensaje del servidor y los detalles por campo.
- `run(accion)` envuelve cada accion: si falla muestra el error en un aviso (toast); si el error es 401 con sesion abierta, la sesion vencio y se vuelve al login.
- Al abrir la pagina se consulta `/sesion`: si hay sesion se muestra la pantalla de internos, si no, el login.
- `VISTAS` relaciona cada boton del menu con su pantalla.
- Todo texto que viene de la API se pasa por `esc()` antes de insertarse en el HTML, para evitar XSS.

## Estructura de archivos

| Archivo | Funcion |
|---|---|
| `router.php` | Router para pruebas locales con el servidor embebido de PHP: redirige `/api/*` al punto de entrada |
| `index.php` | Punto de entrada de la API: carga dependencias, abre sesion, interpreta la ruta y despacha al manejador correspondiente |
| `config.php` | Configuracion general, constantes (`PISOS`, `ACTIVIDADES`), lector de `.env` y endpoint `/config` |
| `cors.php` | Configuracion de CORS con lista de origenes permitidos |
| `conexion.php` | Conexion PDO a MySQL (una por pedido) |
| `errores.php` | Clase `ApiError` (codigo HTTP, mensaje y detalles) |
| `utilidades.php` | Funciones auxiliares: `txt`, `nombrePropio`, `responder`, `leerJson` |
| `login.php` | Sesiones, login, logout, control de acceso por rol |
| `internos.php` | CRUD de internos y registro de salidas y regresos |
| `usuarios.php` | Gestion de usuarios (solo admin) |
| `historial.php` | Historial de altas y bajas |
| `actividad.php` | Registro de entradas y salidas de actividades |
| `servicios.php` | Integracion con servicios externos (geocodificacion y clima) con cache |
| `init_db.php` | Validacion de variables de entorno y creacion automatica de tablas |
| `database.sql` | Script SQL con el esquema de la base de datos |
| `Dockerfile` | Imagen para el despliegue |
| `index.html` | Pagina unica: barra superior, contenedor principal, aviso (toast) y carga de Leaflet |
| `style.css` | Estilos ordenados por secciones, con tema claro y oscuro automatico |
| `app.js` | Estado global, funcion `api()`, manejo de errores (`run`) y arranque de la app |
| `utilidades.js` | Funciones auxiliares del frontend: `seleccionar` (atajo de `querySelector`), `esc` (escapa HTML contra XSS) y `toast` (aviso verde o rojo que desaparece a los 4 segundos) |
| `login.js` | Pantalla de login y cierre de sesion |
| `main.js` | Pantalla de internos: formulario, movimientos y tabla |
| `recuento.js` | Recuento de internos por piso |
| `historial.js` | Historial de altas y bajas |
| `registro.js` | Registro de entradas y salidas de actividades |
| `usuarios.js` | Gestion de usuarios |
| `nav.js` | Menu de navegacion segun el rol |
| `tabla.js` | Tabla generica y etiqueta de ubicacion |
| `clima.js` | Muestra clima y mapa del establecimiento |


## Base de datos

Tablas:

- `usuarios`: usuario, hash de la contrasena y rol (`admin`, `encargado`, `guardia`). El rol es un ENUM porque los roles son fijos, lo que evita una tabla auxiliar y JOINs extra.
- `internos`: legajo (unico), apellidos, nombres, piso y celda.
- `movimientos`: salidas de internos; `regreso` en NULL significa que sigue afuera.
- `historial`: altas y bajas. Se conserva aunque se borre el interno.
- `registro_actividades`: bitacora de cada salida y regreso de actividades.

### Internos

- Validacion: legajo (letras, numeros y guiones, hasta 20), nombres (solo letras, hasta 60, se normalizan a formato titulo), piso (lista blanca) y celda (1 a 10).
- Al crear un interno, el INSERT y el registro en el historial se hacen dentro de una transaccion; si algo falla se hace rollback.
- Al dar de baja, tambien en transaccion, se guarda el historial y luego se borra el interno.
- Un legajo duplicado (error 1062 de MySQL) se traduce a un 409 con mensaje entendible.
- La ubicacion de cada interno se calcula en la consulta: "En celda" si no tiene salida pendiente, "En Transito" si la actividad es Traslado, o el destino de la actividad en otro caso.
- El listado de internos se ordena por piso, luego celda y finalmente apellido paterno.

### Salidas y regresos

- Una salida valida que la actividad y la subactividad existan en `ACTIVIDADES` y que el interno no tenga ya una salida pendiente (409).
- Un regreso exige una salida pendiente y le pone la hora de regreso.
- Ambos eventos se agregan a `registro_actividades` con usuario y fecha.

Actividades disponibles: Estudiar, Trabajo, Sanidad, Visita, Cursos Varios y Traslado, cada una con sus subactividades (definidas en `config.php`).

## Autenticacion y roles

- Sesion por cookie con `HttpOnly`, `SameSite=Lax` y `Secure` cuando se accede por HTTPS.
- Las contrasenas se guardan con `password_hash` y se verifican con `password_verify`.
- El mensaje de error de login es el mismo si falla el usuario o la clave.
- Se regenera el id de sesion al iniciar sesion (evita fijacion de sesion).
- `exigirLogin()` responde 401 sin sesion y `exigirRol()` responde 403 si el rol no corresponde.

| Rol | Permisos |
|---|---|
| admin | Todo, incluida la gestion de usuarios |
| encargado | Altas, bajas, modificaciones, historial y registro de actividades |
| guardia | Consulta de internos y registro de salidas y regresos |

## Seguridad

- Consultas preparadas reales (`ATTR_EMULATE_PREPARES = false`) contra inyeccion SQL.
- Validacion de entradas con listas blancas y expresiones regulares.
- Errores esperados se responden con su codigo (400, 401, 403, 404, 409, 422); cualquier otro fallo devuelve un 500 generico y el detalle va solo al log, con las claves reemplazadas por `[REDACTED]`.
- `display_errors` desactivado en produccion.
- CORS con lista de origenes permitidos y credenciales habilitadas solo para ellos.

## Servicios externos

Se llaman desde el backend (`servicios.php`) y no requieren clave:

- **Nominatim (OpenStreetMap):** convierte la direccion del establecimiento en coordenadas. Se cachea 24 horas.
- **Open-Meteo:** clima actual (temperatura, viento y codigo de clima). Se cachea 10 minutos.

Si un servicio falla, la funcion devuelve `null` y la app sigue funcionando (degradacion elegante). Si falla la geocodificacion se usan `ESTABLECIMIENTO_LAT` y `ESTABLECIMIENTO_LON`. El cache se guarda en archivos temporales del servidor.

### Pantallas

| Pantalla | Quien la ve | Que hace |
|---|---|---|
| Login | Todos | Envia usuario y clave a `POST /login` |
| Internos | Todos | Alta y modificacion (admin, encargado), registro de salidas y regresos, tabla con busqueda y filtro por piso, baja con motivo |
| Recuento | Todos | Totales generales y por piso (en celda y en actividad), calculados con la lista de internos |
| Historial de altas | admin, encargado | Tabla de altas y bajas con filtro por legajo |
| Registro de Actividades | admin, encargado | Tabla de salidas y regresos con filtro por legajo |
| Usuarios | admin | Alta y baja de usuarios (no se puede eliminar el propio) |

El menu (`nav.js`) muestra solo las pantallas que corresponden al rol. Esto es solo comodidad: los permisos reales los controla el backend.

### Clima y mapa

`servicios/clima.js` llama a `/servicios` y muestra:

- La temperatura, la condicion y el viento. Los codigos de clima de Open-Meteo se traducen a texto.
- Un mapa con Leaflet y OpenStreetMap con el marcador del establecimiento, solo si hay coordenadas y la biblioteca cargo.

Si la llamada falla, solo se muestra "Dato no disponible por el momento" y el resto de la app sigue igual.
