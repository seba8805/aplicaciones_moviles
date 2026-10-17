import { api } from '../app.js';
import { esc, seleccionar } from '../utilidades.js';

// Códigos de clima de Open-Meteo traducidos a texto (los no listados se muestran como 'Condición N')
export const CLIMA = {
  0: 'Despejado', 1: 'Mayormente despejado', 2: 'Parcialmente nublado', 3: 'Nublado',
  45: 'Niebla', 51: 'Llovizna', 61: 'Lluvia', 63: 'Lluvia moderada', 80: 'Chubascos', 95: 'Tormenta'
};

// Pide clima y ubicación al backend. Si falla solo muestra 'no disponible': el resto de la app sigue igual
export async function cargarServicios() {
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