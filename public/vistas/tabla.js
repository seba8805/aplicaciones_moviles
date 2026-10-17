import { esc } from '../utilidades.js';

// Tabla genérica: columnas (HTML de encabezado) y filas (arrays de HTML ya escapado)
export function tabla(columnas, filas) {
  const cuerpo = filas.map((fila) => `<tr>${fila.map((celda) => `<td>${celda}</td>`).join('')}</tr>`).join('')
    || `<tr><td colspan="${columnas.length}">Sin resultados</td></tr>`;
  return `<div class="table-wrap"><table><thead><tr>${columnas.map((columna) => `<th>${columna}</th>`).join('')}</tr></thead>
    <tbody>${cuerpo}</tbody></table></div>`;
}

export const etiqueta = (interno) => `<span class="tag ${interno.ubicacion === 'En celda' ? 'in' : ''}">${esc(interno.ubicacion)}</span>`;