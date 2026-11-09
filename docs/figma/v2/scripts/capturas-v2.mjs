// Capturas del estilo nuevo (rama Leonardo-staging): portal del alumno y panel docente, escritorio y móvil, claro y oscuro.
import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { setTimeout as esperar } from 'node:timers/promises';

const BASE = 'http://127.0.0.1:8000';
const SALIDA = process.env.CAP_SALIDA;
const correo = process.env.CAP_CORREO;
const clave = process.env.CAP_CLAVE;
const folio = process.env.CAP_FOLIO;
mkdirSync(SALIDA, { recursive: true });

const edge = spawn('C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', [
  '--headless=new', '--disable-gpu', '--hide-scrollbars', '--remote-debugging-port=9336',
  `--user-data-dir=${process.env.TEMP}\\edge-capturas-${Date.now()}`, 'about:blank',
], { stdio: 'ignore' });

let objetivo;
for (let i = 0; i < 40 && !objetivo; i++) {
  await esperar(500);
  try { objetivo = (await (await fetch('http://127.0.0.1:9336/json')).json()).find((t) => t.type === 'page'); } catch { /* arrancando */ }
}
if (!objetivo) throw new Error('No se pudo conectar a Edge');

const ws = new WebSocket(objetivo.webSocketDebuggerUrl);
await new Promise((r) => (ws.onopen = r));
let id = 0;
const pendientes = new Map();
ws.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && pendientes.has(d.id)) { pendientes.get(d.id)(d); pendientes.delete(d.id); } };
const enviar = (method, params = {}) => new Promise((r) => { const n = ++id; pendientes.set(n, r); ws.send(JSON.stringify({ id: n, method, params })); });
const evaluar = async (e) => (await enviar('Runtime.evaluate', { expression: e, returnByValue: true, awaitPromise: true })).result?.result?.value;
const ir = async (ruta) => { await enviar('Page.navigate', { url: BASE + ruta }); await esperar(2200); };

async function capturar(nombre, ancho, oscuro = false) {
  const movil = ancho < 700;
  await enviar('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: oscuro ? 'dark' : 'light' }] });
  await esperar(300);
  await enviar('Emulation.setDeviceMetricsOverride', { width: ancho, height: 900, deviceScaleFactor: 1, mobile: movil });
  await esperar(500);
  const alto = Math.min(Number(await evaluar('document.documentElement.scrollHeight')) || 900, 6000);
  await enviar('Emulation.setDeviceMetricsOverride', { width: ancho, height: alto, deviceScaleFactor: 1, mobile: movil });
  await esperar(300);
  const r = await enviar('Page.captureScreenshot', { format: 'png' });
  writeFileSync(`${SALIDA}\\${nombre}-${movil ? 'movil' : 'escritorio'}${oscuro ? '-oscuro' : ''}.png`, Buffer.from(r.result.data, 'base64'));
}
const par = async (n) => { await capturar(n, 1440); await capturar(n, 390); };

await enviar('Page.enable');

// ---- Alumno ----
await ir('/alumno/ingreso');
await par('A1-ingreso-folio');
await capturar('A1-ingreso-folio', 1440, true);
await evaluar(`(async()=>{ const t=document.querySelector('meta[name=csrf-token]').content; const r=await fetch('/alumno/ingresar',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':t},body:JSON.stringify({folio_codigo:${JSON.stringify(folio)},folio:${JSON.stringify(folio)},nombre:'Ana Rodríguez'})}); return r.status; })()`)
  .then((s) => console.log('ingresar:', s));
for (const [n, ruta] of [['A2-inicio', '/alumno/inicio'], ['A3-calificaciones', '/alumno/calificaciones']]) {
  await ir(ruta); console.log(n, await evaluar('location.pathname'));
  await par(n);
  await capturar(n, 1440, true);
}
await ir('/alumno/examen'); console.log('examen:', await evaluar('location.pathname'));
await evaluar(`document.querySelector('form[action*=salir] button, [data-theme-toggle]')?.blur()`);

// ---- Docente ----
await ir('/login');
await par('D1-login');
await evaluar(`(() => { const f = document.querySelector('form'); f.querySelector('[name=email]').value = ${JSON.stringify(correo)}; f.querySelector('[name=password]').value = ${JSON.stringify(clave)}; f.submit(); })()`);
await esperar(3500);
console.log('tras login:', await evaluar('location.pathname'));
const rutas = [['D2-panel', '/panel'], ['D3-grupos', '/grupos'], ['D4-banco-preguntas', '/banco-de-preguntas'], ['D5-examenes', '/examenes'],
  ['D6-suscripcion', '/suscripcion'], ['D7-resultados', '/resultados'], ['D8-estadisticas', '/estadisticas']];
for (const [n, ruta] of rutas) { await ir(ruta); console.log(n, await evaluar('location.pathname')); await par(n); }
await ir('/panel'); await capturar('D2-panel', 1440, true);
ws.close(); edge.kill();
console.log('Listo');
