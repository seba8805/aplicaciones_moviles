import { api, run, state } from '../app.js';
import { esc, seleccionar, toast } from '../utilidades.js';
import { nav, enlazarNav } from './nav.js';
import { tabla } from './tabla.js';

// Gestión de usuarios (solo admin)
export async function vistaUsuarios() {
  const usuarios = await api('/usuarios');
  state.app.innerHTML = `${nav('usuarios')}<div class="card"><h2>Gestión de usuarios</h2>
    <form id="f-usr" class="grid">
      <div><label>Usuario</label><input name="usuario" required></div>
      <div><label>Contraseña (mín. 6)</label><input name="password" type="password" required></div>
      <div><label>Rol</label><select name="rol"><option>guardia</option><option>encargado</option><option>admin</option></select></div>
    </form>
    <button class="btn" form="f-usr">Agregar usuario</button></div>
    <div class="card">${tabla(['Usuario', 'Rol', ''], usuarios.map((datosUsuario) => [esc(datosUsuario.usuario), esc(datosUsuario.rol),
      datosUsuario.usuario === state.usuario.usuario ? '' : `<button class="btn dng" data-usr="${datosUsuario.id}">Eliminar</button>`]))}</div>`;
  enlazarNav();

  seleccionar('#f-usr').onsubmit = run(async (evento) => {                         // POST /api/usuarios
    evento.preventDefault();
    await api('/usuarios', { method: 'POST', body: Object.fromEntries(new FormData(evento.target)) });
    toast('Usuario agregado');
    await vistaUsuarios();
  });
  document.querySelectorAll('[data-usr]').forEach((boton) => (boton.onclick = run(async () => {   // DELETE
    if (!confirm('¿Eliminar este usuario?')) return;
    await api('/usuarios/' + boton.dataset.usr, { method: 'DELETE' });
    toast('Usuario eliminado');
    await vistaUsuarios();
  })));
}