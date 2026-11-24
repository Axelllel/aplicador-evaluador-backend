import test from 'node:test';
import assert from 'node:assert/strict';
import { ObservationWindow } from '../../resources/js/proctoring-state.js';
test('un fallo aislado no genera una acusación ni incidencia',()=>{
 const state=new ObservationWindow(); assert.equal(state.observe([],0),null);
 assert.equal(state.observe([{x:.5,y:.5,confidence:.9}],2000),null);
 assert.equal(state.observe([],4000),null);
});
test('ausencia persistente emite evidencia descriptiva sin imágenes',()=>{
 const state=new ObservationWindow();state.observe([],1000);state.observe([],3000);
 const event=state.observe([],5000);assert.equal(event.tipo_alerta,'rostro_no_detectado');assert.equal(event.evidencia_detalle.duracion_segundos,4);assert.equal(event.evidencia_detalle.rostros,0);
 assert.deepEqual(Object.keys(event.evidencia_detalle).sort(),['confianza','duracion_segundos','rostros','tecnologia']);
});
test('varios rostros requieren observaciones consecutivas',()=>{
 const state=new ObservationWindow(),faces=[{x:.2,y:.4,confidence:.8},{x:.7,y:.4,confidence:.9}];
 assert.equal(state.observe(faces,0),null);assert.equal(state.observe(faces,2000),null);assert.equal(state.observe(faces,4000).tipo_alerta,'multiples_rostros');
 assert.equal(state.observe([faces[0]],6000),null);assert.equal(state.observe(faces,8000),null);
});
