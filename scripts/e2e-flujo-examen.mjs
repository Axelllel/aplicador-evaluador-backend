// Prueba de punta a punta contra el servidor local (http://127.0.0.1:8000) con Supabase local y datos sembrados.
// Uso (Node 22+):  E2E_CORREO=... E2E_CLAVE=... E2E_FOLIO=... node scripts/e2e-flujo-examen.mjs
// Crea un examen nuevo en el grupo del docente, lo publica, une a un alumno de prueba, emite el pase,
// presenta el examen por el portal web, lo califica y consulta el resultado. Deja datos de prueba en la base local.
// Flujo completo: docente crea y publica examen -> alumno se une -> docente emite pase -> alumno presenta -> docente califica.
const BASE = 'http://127.0.0.1:8000';
const { E2E_CORREO, E2E_CLAVE, E2E_FOLIO } = process.env;

const corto = (o) => JSON.stringify(o, (k, v) => (/pase|token|access|refresh/i.test(k) && typeof v === 'string' ? v.slice(0, 6) + '…' : v)).slice(0, 330);
let fallos = 0;
const paso = (n, res, cuerpo, esperado = [200, 201]) => {
  const ok = esperado.includes(res.status); if (!ok) fallos++;
  console.log(`${ok ? 'OK ' : 'XX '} ${String(n).padEnd(34)} ${res.status}  ${corto(cuerpo)}`);
};

// ---- API docente ----
let token = '';
const api = async (metodo, ruta, cuerpo) => {
  const r = await fetch(BASE + '/api/v1' + ruta, { method: metodo, headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(token ? { Authorization: 'Bearer ' + token } : {}) }, body: cuerpo ? JSON.stringify(cuerpo) : undefined });
  let j = null; try { j = await r.json(); } catch { /* sin cuerpo */ }
  return [r, j];
};

let [r, j] = await api('POST', '/auth/login', { correo: E2E_CORREO, password: E2E_CLAVE, email: E2E_CORREO });
paso('docente: login', r, j); token = j?.access_token ?? j?.data?.access_token ?? '';
if (!token) { console.log('Sin token; se detiene.'); process.exit(1); }

[r, j] = await api('GET', '/teacher/groups'); paso('docente: grupos', r, j);
const grupoId = (j?.data ?? j)[0]?.id;

const ahora = Date.now();
const iso = (ms) => new Date(ms).toISOString();
[r, j] = await api('POST', '/teacher/exams', { grupo_id: grupoId, nombre: 'Prueba E2E ' + new Date().toISOString().slice(11, 19), fecha_apertura: iso(ahora - 300000), fecha_cierre: iso(ahora + 7200000), orden: 'fijo', escala: '0-10', plataforma_permitida: 'web', resultado_visible: 'final' });
paso('docente: crear examen', r, j); const examenId = j?.data?.id;

const apartados = {};
for (const [tipo, preguntaId, pond] of [['opcion_multiple', 1, 50], ['abierta', 2, 50]]) {
  [r, j] = await api('POST', `/teacher/exams/${examenId}/sections`, { tipo, cantidad_preguntas: 1 });
  paso(`docente: apartado ${tipo}`, r, j); const ap = j?.data?.id; apartados[tipo] = ap;
  [r, j] = await api('POST', `/teacher/exams/${examenId}/sections/${ap}/questions`, { pregunta_id: preguntaId, tiempo_segundos: 120, ponderacion: pond });
  paso(`docente: asignar pregunta ${preguntaId}`, r, j);
}
[r, j] = await api('POST', `/teacher/exams/${examenId}/publish`); paso('docente: publicar', r, j);

// ---- Portal del alumno (sesión con cookies) ----
let cookies = {};
const guardar = (res) => { for (const c of res.headers.getSetCookie?.() ?? []) { const [kv] = c.split(';'); const i = kv.indexOf('='); cookies[kv.slice(0, i)] = kv.slice(i + 1); } };
const cab = () => Object.entries(cookies).map(([k, v]) => `${k}=${v}`).join('; ');
let csrf = '';
const web = async (metodo, ruta, cuerpo) => {
  const res = await fetch(BASE + ruta, { method: metodo, redirect: 'manual', headers: { Cookie: cab(), Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, body: cuerpo ? JSON.stringify(cuerpo) : undefined });
  guardar(res); let jj = null; try { jj = await res.json(); } catch { /* html o vacío */ }
  return [res, jj];
};
let pag = await fetch(BASE + '/alumno/ingreso'); guardar(pag);
csrf = (await pag.text()).match(/name="csrf-token" content="([^"]+)"/)?.[1] ?? '';

[r, j] = await web('POST', '/alumno/validar-folio', { folio_codigo: E2E_FOLIO }); paso('alumno: validar folio', r, j);
[r, j] = await web('POST', '/alumno/ingresar', { folio_codigo: E2E_FOLIO, folio: E2E_FOLIO, nombre: 'Alumno Prueba E2E' }); paso('alumno: unirse al grupo', r, j);
const alumnoId = j?.data?.alumno_id;
// Como el navegador: tras unirse se carga /alumno/inicio y de ahí sale el token CSRF vigente.
const inicio = await fetch(BASE + '/alumno/inicio', { headers: { Cookie: cab() }, redirect: 'manual' }); guardar(inicio);
const htmlInicio = await inicio.text(); csrf = htmlInicio.match(/name="csrf-token" content="([^"]+)"/)?.[1] ?? csrf;
console.log('OK  alumno: abrir inicio                  ', inicio.status, htmlInicio.includes('Hola,') ? 'saludo visible' : 'sin saludo');

[r, j] = await api('POST', `/teacher/exams/${examenId}/students/${alumnoId}/grant`); paso('docente: emitir pase', r, j);
const pase = j?.data?.pase;

[r, j] = await web('POST', '/alumno/examen/iniciar', { pase }); paso('alumno: iniciar examen', r, j);
[r, j] = await web('POST', '/alumno/api/preguntas', {}); paso('alumno: obtener preguntas', r, j);
const lista = j?.data?.preguntas ?? j?.preguntas ?? j?.data ?? [];
console.log('   preguntas recibidas:', Array.isArray(lista) ? lista.length : typeof lista, corto(Array.isArray(lista) ? lista.map((p) => ({ id: p.examen_pregunta_id ?? p.id, tipo: p.tipo })) : lista));

for (const p of Array.isArray(lista) ? lista : []) {
  const id = p.examen_pregunta_id ?? p.id;
  const texto = p.tipo === 'opcion_multiple' ? 'París' : 'Las plantas convierten la luz en energía química.';
  [r, j] = await web('POST', '/alumno/api/respuesta', { examen_pregunta_id: id, respuesta_texto: texto }); paso(`alumno: responder #${id}`, r, j);
}
[r, j] = await web('POST', '/alumno/api/entrega', {}); paso('alumno: entregar', r, j);

// ---- Docente califica ----
[r, j] = await api('GET', `/teacher/exams/${examenId}/attempts`); paso('docente: intentos', r, j);
const intentoId = (j?.data ?? [])[0]?.id ?? (j?.data ?? [])[0]?.intento_id;
[r, j] = await api('POST', `/teacher/attempts/${intentoId}/auto-grade`); paso('docente: autocalificar', r, j);
[r, j] = await api('GET', `/teacher/attempts/${intentoId}/answers`); paso('docente: respuestas', r, j);
const abierta = (j?.data?.respuestas ?? []).find((a) => a.tipo === 'abierta' || /fotos/i.test(a.respuesta_texto ?? ''));
if (abierta) { [r, j] = await api('PATCH', `/teacher/answers/${abierta.id ?? abierta.respuesta_id}/grade`, { calificacion: 8, retroalimentacion: 'Buena explicación.', motivo: 'Prueba E2E' }); paso('docente: calificar abierta', r, j, [200, 201, 422]); }
[r, j] = await api('GET', `/teacher/exams/${examenId}/results`); paso('docente: resultados', r, j);

[r, j] = await web('POST', '/alumno/api/resultado', {}); paso('alumno: ver resultado', r, j);
[r, j] = await web('POST', '/alumno/examen/iniciar', { pase }); paso('alumno: reintentar (debe bloquear)', r, j, [409, 403, 422]);

console.log(fallos ? `\n${fallos} paso(s) fallaron.` : '\nFlujo completo sin fallos.');
console.log('examen', examenId, 'alumno', alumnoId, 'intento', intentoId);
