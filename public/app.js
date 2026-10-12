// =====================================================================
// CLIENTE: consume la API PHP con fetch, sin recargar la página
// =====================================================================
// Atajo para document.querySelector
const seleccionar = (selector, raiz = document) => raiz.querySelector(selector);
const app = seleccionar('#app');
let config = null;        // { pisos, actividades }
let usuario = null;       // { usuario, rol }
let editandoId = null;    // id del interno en edición (null = alta)

// ---------- Utilidades ----------
// Escapa HTML para evitar XSS: todo texto que viene del servidor pasa por aquí antes de mostrarse
const esc = (texto) => String(texto ?? '').replace(/[&<>"']/g, (caracter) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[caracter]));

// Mensaje emergente: verde si salió bien, rojo si hubo un error; desaparece solo
function toast(mensaje, ok = true) {
  const elemento = seleccionar('#toast');
  elemento.textContent = mensaje;
  elemento.className = 'show ' + (ok ? 'ok' : 'err');
  clearTimeout(toast.timer);
  toast.timer = setTimeout(() => (elemento.className = ''), 4000);
}

// Llama a la API. Si respuesta.ok es false, lanza un Error con el mensaje del servidor
async function api(ruta, { method = 'GET', body } = {}) {
  // fetch: pedido HTTP al backend sin recargar la página
  const respuesta = await fetch('/api' + ruta, {
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
const run = (accion) => async (...argumentos) => {
  try {
    await accion(...argumentos);
  } catch (error) {
    // 401 con sesión abierta = la sesión venció → vuelve al login
    if (error.status === 401 && usuario) return salir(true);
    toast(error.message, false);
  }
};

// ---------- Sesión ----------
// Cierra la sesión en el servidor (salvo que ya haya vencido) y vuelve al login
async function salir(silencioso) {
  if (!silencioso) await api('/logout', { method: 'POST' }).catch(() => {});
  usuario = null;
  seleccionar('#topbar').hidden = true;
  vistaLogin();
}
seleccionar('#btn-logout').onclick = () => salir();

// Pantalla de login: envía usuario y contraseña a POST /api/login
function vistaLogin() {
  app.innerHTML = `
    <form class="card center" id="f">
      <h1>Movimientos Internos<br>SPB</h1>
      <label>Usuario</label><input name="usuario" autocomplete="username" required>
      <label>Contraseña</label><input name="password" type="password" autocomplete="current-password" required>
      <button class="btn" style="margin-top:1.2rem">Iniciar sesión</button>
    </form>`;

  seleccionar('#f').onsubmit = run(async (evento) => {
    evento.preventDefault();
    usuario = await api('/login', { method: 'POST', body: Object.fromEntries(new FormData(evento.target)) });
    await vistaPrincipal();
  });
}

// ---------- Pantalla principal ----------
// Pantalla de internos: formulario, movimientos, tabla y widgets de clima y mapa
async function vistaPrincipal() {
  config ??= await api('/config');
  seleccionar('#topbar').hidden = false;
  seleccionar('#who').textContent = `${usuario.usuario} (${usuario.rol})`;
  // Solo admin y encargado ven alta, edición y baja (el servidor lo vuelve a validar)
  const puedeEditar = ['admin', 'encargado'].includes(usuario.rol);

  app.innerHTML = `
    ${nav('internos')}
    <div class="widgets">
      <div class="card" id="clima">Cargando clima…</div>
      <div class="card" id="mapa-card" hidden><div id="mapa" class="map"></div></div>
    </div>

    ${puedeEditar ? `
    <div class="card">
      <h2 id="titulo-form">Alta de interno</h2>
      <form id="f-interno" class="grid">
        <div><label>Legajo</label><input name="legajo" required></div>
        <div><label>Apellido paterno</label><input name="apellido_paterno" required></div>
        <div><label>Apellido materno</label><input name="apellido_materno" required></div>
        <div><label>Nombres</label><input name="nombres" required></div>
        <div><label>Piso</label><select name="piso">${config.pisos.map((nombrePiso) => `<option>${nombrePiso}</option>`).join('')}</select></div>
        <div><label>Celda (1-10)</label><input name="celda" type="number" value="1"></div>
      </form>
      <button class="btn" form="f-interno" id="btn-guardar">Guardar</button>
      <button class="btn sec" id="btn-cancelar" hidden>Cancelar edición</button>
    </div>` : ''}

    <div class="card">
      <h2>Registrar movimiento</h2>
      <form id="f-mov" class="grid">
        <div><label>Interno</label><select name="interno"></select></div>
        <div><label>Actividad</label>
          <select name="actividad"><option value="">Seleccione…</option>
            ${Object.keys(config.actividades).map((actividad) => `<option>${actividad}</option>`).join('')}</select></div>
        <div><label>Destino</label><select name="subactividad"></select></div>
      </form>
      <button class="btn" id="btn-salida">Registrar salida</button>
      <button class="btn sec" id="btn-regreso">Registrar regreso</button>
    </div>

    <div class="card">
      <h2>Internos</h2>
      <div style="display:flex; gap:0.5rem; flex-wrap:wrap">
        <input id="buscar" style="flex:1" placeholder="Buscar por legajo, apellido o nombre…">
        <select id="filtro-piso" style="max-width:200px">
          <option value="">Todos los pisos</option>
          ${config.pisos.map((piso) => `<option value="${piso}">${piso}</option>`).join('')}
        </select>
      </div>
      <div id="tabla" style="margin-top:1rem"></div>
    </div>`;

  // Conecta los botones y formularios con sus acciones
  enlazarEventos(puedeEditar);
  await cargarInternos();
  cargarServicios();            // no se espera: si falla, el resto de la app sigue igual
}

// ---------- Servicios externos (degradación elegante) ----------
// Códigos de clima de Open-Meteo traducidos a texto (los no listados se muestran como 'Condición N')
const CLIMA = { 0: 'Despejado', 1: 'Mayormente despejado', 2: 'Parcialmente nublado', 3: 'Nublado',
  45: 'Niebla', 51: 'Llovizna', 61: 'Lluvia', 63: 'Lluvia moderada', 80: 'Chubascos', 95: 'Tormenta' };

// Pide clima y ubicación al backend. Si falla solo muestra 'no disponible': el resto de la app sigue igual
async function cargarServicios() {
  try {
    const { ubicacion, clima } = await api('/servicios');

    seleccionar('#clima').innerHTML = clima
      ? `<h2>Clima${ubicacion ? ' · ' + esc(ubicacion.nombre) : ''}</h2>
         <b style="font-size:2rem">${clima.temperatura} °C</b>
         <p>${CLIMA[clima.codigo] || 'Condición ' + clima.codigo} · viento ${clima.viento} km/h</p>`
      : '<h2>Clima</h2><p>Dato no disponible por el momento.</p>';

    // El mapa (Leaflet + OpenStreetMap) solo se muestra si hay coordenadas y la biblioteca cargó
    if (ubicacion && window.L) {
      seleccionar('#mapa-card').hidden = false;
      const mapa = L.map('mapa').setView([ubicacion.lat, ubicacion.lon], 14);
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(mapa);
      L.marker([ubicacion.lat, ubicacion.lon]).addTo(mapa).bindPopup(esc(ubicacion.nombre));
    }
  } catch {
    seleccionar('#clima').innerHTML = '<h2>Clima</h2><p>Dato no disponible por el momento.</p>';
  }
}

// ---------- Internos (CRUD) ----------
// Pide la lista (GET /api/internos) y dibuja el desplegable de movimientos y la tabla
async function cargarInternos() {
  // encodeURIComponent escapa el texto de búsqueda para ponerlo en la URL
  const textoBusqueda = encodeURIComponent(seleccionar('#buscar').value.trim());
  let internos = await api('/internos?q=' + textoBusqueda);
  const puedeEditar = ['admin', 'encargado'].includes(usuario.rol);

  // Filtra en memoria si se seleccionó un piso específico
  const pisoSeleccionado = seleccionar('#filtro-piso')?.value;
  if (pisoSeleccionado) {
    internos = internos.filter((interno) => interno.piso === pisoSeleccionado);
  }

  // Lista desplegable del formulario de movimientos
  seleccionar('[name=interno]').innerHTML = internos.map((interno) =>
    `<option value="${interno.id}">${esc(interno.legajo)} · ${esc(interno.apellido_paterno)}, ${esc(interno.nombres)}</option>`).join('');

  const filas = internos.map((interno) => `
    <tr>
      <td>${esc(interno.legajo)}</td>
      <td>${esc(interno.apellido_paterno)} ${esc(interno.apellido_materno)}, ${esc(interno.nombres)}</td>
      <td>${esc(interno.piso)} · ${interno.celda}</td>
      <td><span class="tag ${interno.ubicacion === 'En celda' ? 'in' : ''}">${esc(interno.ubicacion)}</span></td>
      <td>${puedeEditar ? `<button class="btn" data-editar='${esc(JSON.stringify(interno))}'>Editar</button>
          <button class="btn dng" data-borrar="${interno.id}">Eliminar</button>` : ''}</td>
    </tr>`).join('');

  seleccionar('#tabla').innerHTML = `<div class="table-wrap"><table>
    <thead><tr><th>Legajo</th><th>Interno</th><th>Piso · Celda</th><th>Ubicación</th><th></th></tr></thead>
    <tbody>${filas || '<tr><td colspan="5">Sin resultados</td></tr>'}</tbody></table></div>`;

  // Editar: carga el formulario con los datos del interno
  document.querySelectorAll('[data-editar]').forEach((boton) => (boton.onclick = () => {
    // Los datos del interno viajan en el atributo del botón
    const interno = JSON.parse(boton.dataset.editar);
    const formulario = seleccionar('#f-interno');
    ['legajo', 'apellido_paterno', 'apellido_materno', 'nombres', 'piso', 'celda'].forEach((campo) => (formulario[campo].value = interno[campo]));
    // Marca que el próximo guardado será una modificación (PUT)
    editandoId = interno.id;
    seleccionar('#titulo-form').textContent = 'Modificar interno ' + interno.legajo;
    seleccionar('#btn-cancelar').hidden = false;
    formulario.scrollIntoView({ behavior: 'smooth' });
  }));

  // Eliminar (DELETE)
  document.querySelectorAll('[data-borrar]').forEach((boton) => (boton.onclick = run(async () => {
    if (!confirm('¿Dar de baja a este interno? Se eliminarán sus movimientos.')) return;
    const motivo = prompt('Motivo de la baja (opcional):', 'Traslado');
    if (motivo === null) return;                                  // canceló
    await api('/internos/' + boton.dataset.borrar + '?motivo=' + encodeURIComponent(motivo), { method: 'DELETE' });
    toast('Interno dado de baja');
    await cargarInternos();
  })));
}

// Devuelve el formulario al modo alta (POST)
function cancelarEdicion() {
  editandoId = null;
  seleccionar('#f-interno').reset();
  seleccionar('#titulo-form').textContent = 'Alta de interno';
  seleccionar('#btn-cancelar').hidden = true;
}

// ---------- Eventos de la pantalla principal ----------
function enlazarEventos(puedeEditar) {
  enlazarNav();
  seleccionar('#buscar').oninput = run(cargarInternos);
  seleccionar('#filtro-piso').onchange = run(cargarInternos);

  if (puedeEditar) {
    // POST (alta) o PUT (modificación) según haya un interno en edición
    seleccionar('#f-interno').onsubmit = run(async (evento) => {
      evento.preventDefault();
      const datos = Object.fromEntries(new FormData(evento.target));
      // El formulario entrega texto; la API espera un número
      datos.celda = datos.celda === '' ? null : Number(datos.celda);
      // PUT si se está editando un interno, POST si es un alta nueva
      if (editandoId) {
        await api('/internos/' + editandoId, { method: 'PUT', body: datos });
        toast('Interno modificado');
      } else {
        await api('/internos', { method: 'POST', body: datos });
        toast('Interno creado');
      }
      cancelarEdicion();
      await cargarInternos();
    });
    seleccionar('#btn-cancelar').onclick = cancelarEdicion;
  }

  const formulario = seleccionar('#f-mov');
  // Al elegir una actividad se cargan sus destinos
  formulario.actividad.onchange = () => {
    formulario.subactividad.innerHTML = (config.actividades[formulario.actividad.value] || []).map((destino) => `<option>${destino}</option>`).join('');
  };

  seleccionar('#btn-salida').onclick = run(async () => {
    await api(`/internos/${formulario.interno.value}/salida`, {
      method: 'POST',
      body: { actividad: formulario.actividad.value, subactividad: formulario.subactividad.value },
    });
    toast('Salida registrada');
    await cargarInternos();
  });

  seleccionar('#btn-regreso').onclick = run(async () => {
    await api(`/internos/${formulario.interno.value}/regreso`, { method: 'POST', body: {} });
    toast('Regreso registrado');
    await cargarInternos();
  });
}

// ---------- Navegación entre pantallas (según el rol) ----------
const VISTAS = { internos: vistaPrincipal, recuento: vistaRecuento, historial: vistaHistorial, usuarios: vistaUsuarios };

function nav(actual) {
  const items = [['internos', 'Internos'], ['recuento', 'Recuento']];
  if (['admin', 'encargado'].includes(usuario.rol)) items.push(['historial', 'Historial']);
  if (usuario.rol === 'admin') items.push(['usuarios', 'Usuarios']);
  return `<div class="card">${items.map(([vista, titulo]) =>
    `<button class="btn ${vista === actual ? '' : 'sec'}" data-vista="${vista}">${titulo}</button>`).join('')}</div>`;
}

function enlazarNav() {
  document.querySelectorAll('[data-vista]').forEach((boton) => (boton.onclick = run(() => VISTAS[boton.dataset.vista]())));
}

// Tabla genérica: columnas (HTML de encabezado) y filas (arrays de HTML ya escapado)
function tabla(columnas, filas) {
  const cuerpo = filas.map((fila) => `<tr>${fila.map((celda) => `<td>${celda}</td>`).join('')}</tr>`).join('')
    || `<tr><td colspan="${columnas.length}">Sin resultados</td></tr>`;
  return `<div class="table-wrap"><table><thead><tr>${columnas.map((columna) => `<th>${columna}</th>`).join('')}</tr></thead>
    <tbody>${cuerpo}</tbody></table></div>`;
}

const etiqueta = (interno) => `<span class="tag ${interno.ubicacion === 'En celda' ? 'in' : ''}">${esc(interno.ubicacion)}</span>`;

// ---------- Recuento por piso (se calcula con la lista de internos) ----------
async function vistaRecuento() {
  config ??= await api('/config');
  const todos = await api('/internos');
  const afuera = todos.filter((interno) => interno.ubicacion !== 'En celda').length;
  const stat = (titulo, cantidad) => `<div class="stat">${titulo}<b>${cantidad}</b></div>`;

  let html = `${nav('recuento')}<div class="card"><h2>Recuento</h2>
    <div class="stats">${stat('Total general', todos.length)}${stat('En celdas', todos.length - afuera)}${stat('En movimiento', afuera)}</div>`;

  for (const piso of config.pisos) {
    const delPiso = todos.filter((interno) => interno.piso === piso);
    const fuera = delPiso.filter((interno) => interno.ubicacion !== 'En celda').length;
    html += `<h3>${esc(piso)} <small>— Total: ${delPiso.length} · En celda: ${delPiso.length - fuera} · En actividad: ${fuera}</small></h3>`
      + tabla(['Celda', 'Legajo', 'Interno', 'Ubicación'], delPiso.map((interno) =>
        [interno.celda, esc(interno.legajo), `${esc(interno.apellido_paterno)} ${esc(interno.apellido_materno)}, ${esc(interno.nombres)}`, etiqueta(interno)]));
  }
  app.innerHTML = html + '</div>';
  enlazarNav();
}

// ---------- Historial de altas y bajas ----------
async function vistaHistorial() {
  app.innerHTML = `${nav('historial')}<div class="card"><h2>Historial de altas y bajas</h2>
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

// ---------- Gestión de usuarios (solo admin) ----------
async function vistaUsuarios() {
  const usuarios = await api('/usuarios');
  app.innerHTML = `${nav('usuarios')}<div class="card"><h2>Gestión de usuarios</h2>
    <form id="f-usr" class="grid">
      <div><label>Usuario</label><input name="usuario" required></div>
      <div><label>Contraseña (mín. 6)</label><input name="password" type="password" required></div>
      <div><label>Rol</label><select name="rol"><option>guardia</option><option>encargado</option><option>admin</option></select></div>
    </form>
    <button class="btn" form="f-usr">Agregar usuario</button></div>
    <div class="card">${tabla(['Usuario', 'Rol', ''], usuarios.map((datosUsuario) => [esc(datosUsuario.usuario), esc(datosUsuario.rol),
      datosUsuario.usuario === usuario.usuario ? '' : `<button class="btn dng" data-usr="${datosUsuario.id}">Eliminar</button>`]))}</div>`;
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

// ---------- Inicio: si hay sesión activa en el servidor, entra directo ----------
// Al abrir la página pregunta al servidor si ya hay una sesión iniciada; si no, muestra el login
api('/sesion')
  .then((datosSesion) => { usuario = datosSesion; return vistaPrincipal(); })
  .catch(() => vistaLogin());
