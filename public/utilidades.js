// Atajo para document.querySelector
export const seleccionar = (selector, raiz = document) => raiz.querySelector(selector);

// Escapa HTML para evitar XSS: todo texto que viene del servidor pasa por aquí antes de mostrarse
export const esc = (texto) => String(texto ?? '').replace(/[&<>"']/g, (caracter) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[caracter]));

// Mensaje emergente: verde si salió bien, rojo si hubo un error; desaparece solo
export function toast(mensaje, ok = true) {
  const elemento = seleccionar('#toast');
  elemento.textContent = mensaje;
  elemento.className = 'show ' + (ok ? 'ok' : 'err');
  clearTimeout(toast.timer);
  toast.timer = setTimeout(() => (elemento.className = ''), 4000);
}