import { api, run, state } from '../app.js';
import { esc, seleccionar } from '../utilidades.js';
import { nav, enlazarNav } from './nav.js';
import { tabla } from './tabla.js';

// Historial de altas y bajas
export async function vistaHistorial() {
  state.app.innerHTML = `${nav('historial')}<div class="card"><h2>Historial de altas y bajas</h2>
    <input id="filtro" placeholder="Filtrar por legajo…"><div id="hist" style="margin-top:1rem"></div></div>`;
  enlazarNav();

  const cargar = run(async () => {
    const filas = await api('/historial?legajo=' + encodeURIComponent(seleccionar('#filtro').value.trim()));
    seleccionar('#hist').innerHTML = tabla(['Fecha', 'Tipo', 'Legajo', 'Interno', 'Detalles', 'Registró'],
      filas.map((registro) => [esc(registro.fecha), esc(registro.tipo), esc(registro.legajo), esc(registro.interno), esc(registro.detalles), esc(registro.usuario)]));
  });
  seleccionar('#filtro').oninput = cargar;
  await cargar();
}