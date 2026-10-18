import { api, run, state } from '../app.js';
import { esc, seleccionar } from '../utilidades.js';
import { nav, enlazarNav } from './nav.js';
import { tabla } from './tabla.js';

// Lista las entradas y salidas de las actividades.
export async function ListarRegistrosDeActividades() {
  state.app.innerHTML = `${nav('registro')}<div class="card"><h2>Registro de entradas y salidas de actividades</h2>
    <input id="filtro-legajo" placeholder="Filtrar por legajo…"><div id="reg-act" style="margin-top:1rem"></div></div>`;
  enlazarNav();

  const cargar = run(async () => {
    const filas = await api('/registro-actividades?legajo=' + encodeURIComponent(seleccionar('#filtro-legajo').value.trim()));
    seleccionar('#reg-act').innerHTML = tabla([
      'Fecha', 'Acción', 'Actividad', 'Destino', 'Legajo', 'Interno', 'Registró'
    ], filas.map((fila) => [
      esc(fila.fecha),
      esc(fila.accion === 'salida' ? 'Salida' : 'Regreso'),
      esc(fila.actividad),
      esc(fila.subactividad ?? '-'),
      esc(fila.legajo),
      esc(fila.nombre),
      esc(fila.usuario)
    ]));
  });

  seleccionar('#filtro-legajo').oninput = cargar;
  await cargar();
}
