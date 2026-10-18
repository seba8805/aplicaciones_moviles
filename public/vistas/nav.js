import { run, state, VISTAS } from '../app.js';

// ---------- Navegación entre pantallas (según el rol) ----------
export function nav(actual) {
  const items = [['internos', 'Internos'], ['recuento', 'Recuento']];
  if (['admin', 'encargado'].includes(state.usuario.rol)) {
    items.push(['historial', 'Historial de altas']);
    items.push(['registro', 'Registro de Actividades']);
  }
  if (state.usuario.rol === 'admin') items.push(['usuarios', 'Usuarios']);
  return `<div class="card">${items.map(([vista, titulo]) =>
    `<button class="btn ${vista === actual ? '' : 'sec'}" data-vista="${vista}">${titulo}</button>`).join('')}</div>`;
}

export function enlazarNav() {
  document.querySelectorAll('[data-vista]').forEach((boton) => (boton.onclick = run(() => VISTAS[boton.dataset.vista]())));
}
