// Importaciones
import { seleccionar, toast } from './utilidades.js';
import { salir, vistaLogin } from './vistas/login.js';
import { vistaPrincipal } from './vistas/main.js';
import { vistaRecuento } from './vistas/recuento.js';
import { vistaHistorial } from './vistas/historial.js';
import { ListarRegistrosDeActividades } from './vistas/registro.js';
import { vistaUsuarios } from './vistas/usuarios.js';

// Estado de la app
export const state = {
  app: seleccionar('#app'), // Atajo para el contenedor principal
  config: null,             // { pisos, actividades }
  usuario: null,            // { usuario, rol }
};

// Llama a la API. Si respuesta.ok es false, lanza un Error con el mensaje del servidor
export async function api(ruta, { method = 'GET', body } = {}) {
  // fetch: pedido HTTP al backend sin recargar la página
  const respuesta = await fetch('api' + ruta, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  // Lee el JSON; si la respuesta no es JSON usa {} para no romper
  const datos = await respuesta.json().catch(() => ({}));

  // respuesta.ok es false en 4xx y 5xx: se arma un Error con el mensaje del servidor y se lanza
  if (!respuesta.ok) {
    const detalle = datos.detalles ? ': ' + Object.values(datos.detalles).join(' · ') : '';
    const error = new Error((datos.error || 'Error ' + respuesta.status) + detalle);
    error.status = respuesta.status;
    throw error;
  }
  return datos;
}

// Ejecuta una acción y muestra cualquier error al usuario
export const run = (accion) => async (...argumentos) => {
  try {
    await accion(...argumentos);
  } catch (error) {
    // 401 con sesión abierta = la sesión venció → vuelve al login
    if (error.status === 401 && state.usuario) return salir(true);
    toast(error.message, false);
  }
};

// Se usa en la la nav.js para vincular botones con el archivo de vista que se muestra
export const VISTAS = {
  internos: vistaPrincipal,
  recuento: vistaRecuento,
  historial: vistaHistorial,
  registro: ListarRegistrosDeActividades,
  usuarios: vistaUsuarios,
};

// al abrir la página pregunta al servidor si ya hay una sesión iniciada; si no, muestra el login
export function iniciar_app() {
  return api('/sesion')
    .then((datosSesion) => { state.usuario = datosSesion; return vistaPrincipal(); })
    .catch(() => vistaLogin());
}

// Iniciamos la app
iniciar_app();
