import { seleccionar, esc, toast } from '../utilidades.js';
import { api, run, state } from '../app.js';
import { cargarServicios } from '../servicios/clima.js';
import { nav, enlazarNav } from './nav.js';

let editandoId = null;
let internosCargados = [];

// Pantalla de internos: formulario, movimientos, tabla y widgets de clima y mapa
export async function vistaPrincipal() {
  state.config ??= await api('/config');
  seleccionar('#topbar').hidden = false;
  seleccionar('#who').textContent = `${state.usuario.usuario} (${state.usuario.rol})`;
  const puedeEditar = ['admin', 'encargado'].includes(state.usuario.rol);

  state.app.innerHTML = `
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
        <div><label>Piso</label><select name="piso">${state.config.pisos.map((nombrePiso) => `<option>${nombrePiso}</option>`).join('')}</select></div>
        <div><label>Celda (1-10)</label><input name="celda" type="number" min="1" max="10" value="1"></div>
      </form>
      <p id="aviso-legajo" style="margin-top:0.75rem; color:#f87171; font-weight:500;" hidden>
        Ya existe un interno con ese legajo: valor duplicado.
      </p>
      <div style="margin-top:1rem; display:flex; gap:0.5rem;">
        <button class="btn sec" form="f-interno" id="btn-guardar" disabled>Guardar</button>
        <button class="btn sec" id="btn-cancelar" hidden>Cancelar edición</button>
      </div>
    </div>` : ''}

    <div class="card">
      <h2>Registrar movimiento</h2>
      <form id="f-mov" class="grid">
        <div><label>Interno</label><select name="interno"></select></div>
        <div id="campos-actividad" style="display:contents">
          <div><label>Actividad</label>
            <select name="actividad"><option value="">Seleccione…</option>
              ${Object.keys(state.config.actividades).map((actividad) => `<option>${actividad}</option>`).join('')}</select></div>
          <div><label>Destino</label><select name="subactividad"></select></div>
        </div>
      </form>
      <p id="aviso-salida" style="margin-top:0.75rem; color:#f87171; font-weight:500;" hidden>
        El interno tiene una salida pendiente de regreso.
      </p>
      <div style="margin-top:1rem; display:flex; gap:0.5rem;">
        <button class="btn" id="btn-salida" hidden>Registrar salida</button>
        <button class="btn" id="btn-regreso" hidden>Registrar regreso</button>
      </div>
    </div>

    <div class="card">
      <h2>Internos</h2>
      <div style="display:flex; gap:0.5rem; flex-wrap:wrap">
        <input id="buscar" style="flex:1" placeholder="Buscar por legajo, apellido o nombre…">
        <select id="filtro-piso" style="max-width:200px">
          <option value="">Todos los pisos</option>
          ${state.config.pisos.map((piso) => `<option value="${piso}">${piso}</option>`).join('')}
        </select>
      </div>
      <div id="tabla" style="margin-top:1rem"></div>
    </div>`;

  enlazarEventos(puedeEditar);
  await cargarInternos();
  cargarServicios();
}

// ---------- Internos (CRUD) ----------
// Pide la lista (GET /api/internos) y dibuja el desplegable de movimientos y la tabla
export async function cargarInternos() {
  // encodeURIComponent escapa el texto de búsqueda para ponerlo en la URL
  const textoBusqueda = encodeURIComponent(seleccionar('#buscar').value.trim());
  let internos = await api('/internos?q=' + textoBusqueda);
  internosCargados = internos;
  const puedeEditar = ['admin', 'encargado'].includes(state.usuario.rol);

  // Filtra en memoria si se seleccionó un piso específico
  const pisoSeleccionado = seleccionar('#filtro-piso')?.value;
  if (pisoSeleccionado) {
    internos = internos.filter((interno) => interno.piso === pisoSeleccionado);
  }

  // Lista desplegable del formulario de movimientos
  seleccionar('[name=interno]').innerHTML = '<option value="">Seleccione…</option>' + internos.map((interno) =>
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
    validarFormularioInterno()
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

// Comprueba que todos los campos obligatorios tengan valor y que el legajo no esté duplicado
function validarFormularioInterno() {
  const formulario = seleccionar('#f-interno');
  if (!formulario) return;

  const legajo = formulario.legajo.value.trim();
  const apellidoPaterno = formulario.apellido_paterno.value.trim();
  const apellidoMaterno = formulario.apellido_materno.value.trim();
  const nombres = formulario.nombres.value.trim();
  const celda = formulario.celda.value.trim();
  const aviso = seleccionar('#aviso-legajo');
  const btnGuardar = seleccionar('#btn-guardar');
  const esDuplicado = internosCargados.some((i) => i.legajo === legajo && i.id !== editandoId);
  if (aviso) aviso.hidden = !esDuplicado || legajo === '';
  const camposCompletos = 
    legajo !== '' && 
    apellidoPaterno !== '' && 
    apellidoMaterno !== '' && 
    nombres !== '' && 
    celda !== '' && 
    Number(celda) >= 1 && 
    Number(celda) <= 10;

  const esValido = camposCompletos && !esDuplicado;

  if (btnGuardar) {
    btnGuardar.disabled = !esValido;
    if (esValido) {
      btnGuardar.classList.remove('sec'); // Vuelve al azul principal
    } else {
      btnGuardar.classList.add('sec');    // Se pone gris
    }
  }
}

// Desactiva actividad/destino si el interno seleccionado ya está fuera de su celda
function actualizarFormularioMovimiento() {
  const formulario = seleccionar('#f-mov');
  if (!formulario || !formulario.interno) return;
  const idSeleccionado = formulario.interno.value ? Number(formulario.interno.value) : null;
  const aviso = seleccionar('#aviso-salida');
  const btnSalida = seleccionar('#btn-salida');
  const btnRegreso = seleccionar('#btn-regreso');
  if (!idSeleccionado) {
    formulario.actividad.disabled = true;
    formulario.subactividad.disabled = true;
    formulario.actividad.value = '';
    formulario.subactividad.innerHTML = '';
    if (aviso) aviso.hidden = true;
    if (btnSalida) btnSalida.hidden = true;
    if (btnRegreso) btnRegreso.hidden = true;
    return;
  }
  const interno = internosCargados.find((item) => item.id === idSeleccionado);
  const yaSalio = Boolean(interno && interno.ubicacion !== 'En celda');
  formulario.actividad.disabled = yaSalio;
  formulario.subactividad.disabled = yaSalio;
  if (aviso) aviso.hidden = !yaSalio;
  if (btnSalida) btnSalida.hidden = yaSalio;
  if (btnRegreso) btnRegreso.hidden = !yaSalio;
}

// Devuelve el formulario al modo alta (POST)
export function cancelarEdicion() {
  editandoId = null;
  seleccionar('#f-interno').reset();
  seleccionar('#titulo-form').textContent = 'Alta de interno';
  seleccionar('#btn-cancelar').hidden = true;
  validarFormularioInterno();
}

// ---------- Eventos de la pantalla principal ----------
export function enlazarEventos(puedeEditar) {
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
    seleccionar('#f-interno').oninput = (evento) => {
      const formulario = evento.currentTarget;
      if (evento.target.name === 'celda') {
        if (Number(formulario.celda.value) > 10) formulario.celda.value = 10;
        if (Number(formulario.celda.value) < 1 && formulario.celda.value !== '') formulario.celda.value = 1;
      }
      validarFormularioInterno();
    };
    seleccionar('#btn-cancelar').onclick = cancelarEdicion;
  }

  const formulario = seleccionar('#f-mov');
  // Al elegir una actividad se cargan sus destinos
  formulario.actividad.onchange = () => {
    formulario.subactividad.innerHTML = (state.config.actividades[formulario.actividad.value] || []).map((destino) => `<option>${destino}</option>`).join('');
  };

  // Evalúa si el interno seleccionado ya está afuera
  formulario.interno.onchange = actualizarFormularioMovimiento;
  formulario.actividad.onchange = () => {
    formulario.subactividad.innerHTML = (state.config.actividades[formulario.actividad.value] || []).map((destino) => `<option>${destino}</option>`).join('');
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
