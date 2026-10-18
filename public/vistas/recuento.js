import { api, state } from '../app.js';
import { esc } from '../utilidades.js';
import { nav, enlazarNav } from './nav.js';
import { tabla, etiqueta } from './tabla.js';

// Recuento por piso (se calcula con la lista de internos)
export async function vistaRecuento() {
  state.config ??= await api('/config');
  const todos = await api('/internos');
  const afuera = todos.filter((interno) => interno.ubicacion !== 'En celda').length;
  const stat = (titulo, cantidad) => `<div class="stat">${titulo}<b>${cantidad}</b></div>`;

  let html = `${nav('recuento')}<div class="card"><h2>Recuento</h2>
    <div class="stats">${stat('Total general', todos.length)}${stat('En celdas', todos.length - afuera)}${stat('En movimiento', afuera)}</div>`;

  for (const piso of state.config.pisos) {
    const delPiso = todos.filter((interno) => interno.piso === piso);
    const fuera = delPiso.filter((interno) => interno.ubicacion !== 'En celda').length;
    html += `<h3>${esc(piso)} <small>— Total: ${delPiso.length} · En celda: ${delPiso.length - fuera} · En actividad: ${fuera}</small></h3>`
      + tabla(['Celda', 'Legajo', 'Interno', 'Ubicación'], delPiso.map((interno) =>
        [interno.celda, esc(interno.legajo), `${esc(interno.apellido_paterno)} ${esc(interno.apellido_materno)}, ${esc(interno.nombres)}`, etiqueta(interno)]));
  }
  state.app.innerHTML = html + '</div>';
  enlazarNav();
}
