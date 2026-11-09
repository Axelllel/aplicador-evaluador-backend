// Genera un HTML autocontenido por pantalla (CSS e imágenes incrustados) para importarlo con html.to.design.
import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { setTimeout as esperar } from 'node:timers/promises';

const BASE = 'http://127.0.0.1:8000';
const SALIDA = process.env.CAP_SALIDA;
const { CAP_CORREO: correo, CAP_CLAVE: clave, CAP_FOLIO: folio } = process.env;
mkdirSync(SALIDA, { recursive: true });

const edge = spawn('C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', [
  '--headless=new', '--disable-gpu', '--hide-scrollbars', '--remote-debugging-port=9337',
  `--user-data-dir=${process.env.TEMP}\\edge-html-${Date.now()}`, 'about:blank',
], { stdio: 'ignore' });
let objetivo;
for (let i = 0; i < 40 && !objetivo; i++) {
  await esperar(500);
  try { objetivo = (await (await fetch('http://127.0.0.1:9337/json')).json()).find((t) => t.type === 'page'); } catch { /* arrancando */ }
}
if (!objetivo) throw new Error('No se pudo conectar a Edge');
const ws = new WebSocket(objetivo.webSocketDebuggerUrl);
await new Promise((r) => (ws.onopen = r));
let id = 0; const pend = new Map();
ws.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && pend.has(d.id)) { pend.get(d.id)(d); pend.delete(d.id); } };
const enviar = (method, params = {}) => new Promise((r) => { const n = ++id; pend.set(n, r); ws.send(JSON.stringify({ id: n, method, params })); });
const evaluar = async (e) => (await enviar('Runtime.evaluate', { expression: e, returnByValue: true, awaitPromise: true })).result?.result?.value;
const ir = async (ruta) => { await enviar('Page.navigate', { url: BASE + ruta }); await esperar(2200); };

// Se ejecuta dentro de la página: devuelve el documento con CSS, imágenes y fuentes locales incrustados.
const EMPAQUETAR = `(async () => {
  const aDataUri = async (url) => {
    try {
      const r = await fetch(url); if (!r.ok) return null;
      const b = await r.blob(); if (b.size > 400000) return null;
      return await new Promise((ok) => { const f = new FileReader(); f.onload = () => ok(f.result); f.readAsDataURL(b); });
    } catch { return null; }
  };
  const incrustarUrls = async (css, base) => {
    const re = /url\\(\\s*(['"]?)(?!data:|https?:|#)([^'")]+)\\1\\s*\\)/g; let salida = '', ultimo = 0, m;
    while ((m = re.exec(css))) {
      const d = await aDataUri(new URL(m[2], base).href);
      salida += css.slice(ultimo, m.index) + (d ? 'url(' + d + ')' : m[0]); ultimo = re.lastIndex;
    }
    return salida + css.slice(ultimo);
  };
  const doc = document.documentElement.cloneNode(true);
  for (const s of [...doc.querySelectorAll('script, link[rel=modulepreload], link[rel=preload]')]) s.remove();
  for (const l of [...doc.querySelectorAll('link[rel=stylesheet]')]) {
    try {
      const url = new URL(l.getAttribute('href'), location.href).href;
      let css = await (await fetch(url)).text();
      css = await incrustarUrls(css, url);
      const st = document.createElement('style'); st.textContent = css; l.replaceWith(st);
    } catch { /* se deja el enlace */ }
  }
  for (const img of [...doc.querySelectorAll('img')]) {
    const d = await aDataUri(new URL(img.getAttribute('src'), location.href).href); if (d) img.setAttribute('src', d);
  }
  // Valores escritos en campos (el clon no los conserva).
  const reales = [...document.querySelectorAll('input, textarea')], clones = [...doc.querySelectorAll('input, textarea')];
  reales.forEach((e, i) => { if (clones[i] && e.value) clones[i].setAttribute('value', e.value); });
  doc.querySelector('head').insertAdjacentHTML('afterbegin', '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">');
  return '<!doctype html>\\n' + doc.outerHTML;
})()`;

const volcar = async (nombre) => {
  const html = await evaluar(EMPAQUETAR);
  writeFileSync(`${SALIDA}\\${nombre}.html`, html, 'utf8');
  console.log(nombre, Math.round(html.length / 1024) + ' KB');
};

await enviar('Page.enable');
await enviar('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });

await ir('/alumno/ingreso'); await volcar('A1-ingreso-folio');
await evaluar(`(async()=>{ const t=document.querySelector('meta[name=csrf-token]').content; const r=await fetch('/alumno/ingresar',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':t},body:JSON.stringify({folio_codigo:${JSON.stringify(folio)},folio:${JSON.stringify(folio)},nombre:'Ana Rodríguez'})}); return r.status; })()`);
await ir('/alumno/inicio'); await volcar('A2-inicio');
await ir('/alumno/calificaciones'); await volcar('A3-calificaciones');

await ir('/login'); await volcar('D1-login');
await evaluar(`(() => { const f = document.querySelector('form'); f.querySelector('[name=email]').value = ${JSON.stringify(correo)}; f.querySelector('[name=password]').value = ${JSON.stringify(clave)}; f.submit(); })()`);
await esperar(3500);
for (const [n, ruta] of [['D2-panel', '/panel'], ['D3-grupos', '/grupos'], ['D4-banco-preguntas', '/banco-de-preguntas'], ['D5-examenes', '/examenes'],
  ['D6-suscripcion', '/suscripcion'], ['D7-resultados', '/resultados'], ['D8-estadisticas', '/estadisticas']]) { await ir(ruta); await volcar(n); }
ws.close(); edge.kill();
console.log('Listo');
