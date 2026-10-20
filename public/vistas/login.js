import { api, run, state } from '../app.js';
import { seleccionar } from '../utilidades.js';
import { vistaPrincipal } from './main.js';

// Cierra la sesión en el servidor (salvo que ya haya vencido) y vuelve al login
export async function salir(silencioso) {
  if (!silencioso) await api('/logout', { method: 'POST' }).catch(() => {});
  state.usuario = null;
  seleccionar('#topbar').hidden = true;
  seleccionar('#who').textContent = '';
  vistaLogin();
}

// Pantalla de login: envía usuario y contraseña a POST /api/login
export function vistaLogin() {
  seleccionar('#topbar').hidden = true;
  seleccionar('#who').textContent = '';
  state.app.innerHTML = `
    <form class="card center" id="f">
      <h1>Movimientos Internos<br>SPB</h1>
      <label>Usuario</label><input name="usuario" autocomplete="username" required>
      <label>Contraseña</label><input name="password" type="password" autocomplete="current-password" required>
      <button class="btn btn-login">Iniciar sesión</button>
    </form>`;

  seleccionar('#f').onsubmit = run(async (evento) => {
    evento.preventDefault();
    state.usuario = await api('/login', { method: 'POST', body: Object.fromEntries(new FormData(evento.target)) });
    await vistaPrincipal();
  });
}

seleccionar('#btn-logout').onclick = () => salir();
