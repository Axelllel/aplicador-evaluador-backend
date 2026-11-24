import { test, expect } from '@playwright/test';
test('cámara local, respuestas recuperadas y diseño adaptable',async({page},testInfo)=>{
 const errors=[],events=[];page.on('pageerror',error=>errors.push(error.message));
 await page.route('**/alumno/api/**',async route=>{
  const url=route.request().url();
  const time={servidor_ahora:new Date().toISOString(),fecha_limite:new Date(Date.now()+600000).toISOString(),estado:'en_curso',examen_pregunta_id:1,pregunta_fecha_limite:new Date(Date.now()+60000).toISOString(),puede_responder:true,pregunta:{enunciado:'Explica los cambios de la segunda guerra mundial',tipo:'abierta',examen_pregunta_id:1}};
  let data={};if(url.endsWith('/preguntas'))data={preguntas:[{examen_pregunta_id:1,tipo:'abierta',tiempo_segundos:60}],respuestas:{1:'Mi respuesta conservada'},tiempo:time};
  else if(url.endsWith('/tiempo')||url.endsWith('/abrir-pregunta'))data=time;
  if(url.endsWith('/incidencia'))events.push(route.request().postDataJSON());
  await route.fulfill({json:{data}});
 });
 await page.goto('/qa-exam.html');
 await expect(page.locator('#bbb-free-answer')).toHaveValue('Mi respuesta conservada');
 await page.locator('#bbb-camera').click();
 await expect(page.locator('#bbb-proctoring-status')).toContainText('Cámara activa',{timeout:40000});
 await expect.poll(()=>events.some(event=>event.tipo_alerta==='rostro_no_detectado'),{timeout:15000}).toBe(true);
 expect(events.find(event=>event.tipo_alerta==='rostro_no_detectado').evidencia_detalle.rostros).toBe(0);
 await expect(page.locator('#bbb-free-answer')).toHaveValue('Mi respuesta conservada');
 for(const width of [1366,768,375]){
  await page.setViewportSize({width,height:900});
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth)).toBe(true);
  expect(await page.locator('h1').evaluate(element=>getComputedStyle(element).fontSize)).toBe('24px');
  expect(await page.locator('#bbb-camera').evaluate(element=>element.getBoundingClientRect().height)).toBeGreaterThanOrEqual(44);
  await page.screenshot({path:testInfo.outputPath(`examen-${width}.png`),fullPage:true});
 }
 // Zoom equivalente al 200 %: se duplica el tamaño base de la tipografía.
 await page.evaluate(()=>document.documentElement.style.fontSize='200%');
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth)).toBe(true);
 expect(errors).toEqual([]);
});
test('acceso docente y selección de perfil sin desbordamiento',async({page},testInfo)=>{
 for(const name of ['login','perfiles'])for(const width of [1366,375]){
  await page.setViewportSize({width,height:900});await page.goto(`/qa-${name}.html`);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth)).toBe(true);
  const externalFonts=await page.evaluate(()=>performance.getEntriesByType('resource').filter(resource=>/fonts.googleapis|fonts.gstatic|fonts.bunny/.test(resource.name)).map(resource=>resource.name));
  expect(externalFonts).toEqual([]);
  await page.screenshot({path:testInfo.outputPath(`${name}-${width}.png`),fullPage:true});
 }
});
