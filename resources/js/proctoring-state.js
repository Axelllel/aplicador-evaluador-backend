/** Las detecciones son indicios para revisión humana, nunca una prueba de fraude. */
export class ObservationWindow {
  constructor() { this.type = null; this.since = 0; this.samples = 0; this.previous = null; }
  observe(faces, timestamp) {
    const first = faces[0];
    const moving = first && this.previous && Math.hypot(first.x - this.previous.x, first.y - this.previous.y) > .3;
    this.previous = first || null;
    const type = !faces.length ? 'rostro_no_detectado' : faces.length > 1 ? 'multiples_rostros' : moving ? 'movimiento' : null;
    if (type !== this.type) { this.type = type; this.since = timestamp; this.samples = 0; }
    this.samples++;
    if (!type || this.samples < 3 || timestamp - this.since < 4000) return null;
    return { tipo_alerta: type, nivel_severidad: 'media', evidencia_detalle: { tecnologia: 'MediaPipe BlazeFace 0.10.32', rostros: faces.length, duracion_segundos: Math.round((timestamp-this.since)/1000), confianza: first?.confidence || 0 } };
  }
}
