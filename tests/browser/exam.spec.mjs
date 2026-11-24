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

test('el administrador tiene formulario independiente y no aparece en los accesos públicos',async({page},testInfo)=>{
 await page.goto('/qa-perfiles.html');
 await expect(page.locator('nav > a')).toHaveCount(2);
 await expect(page.locator('a[href*="administracion"]')).toHaveCount(0);
 await page.goto('/qa-login.html');
 await expect(page.locator('form[action$="/login"]')).toHaveCount(1);
 await expect(page.locator('a[href*="administracion"]')).toHaveCount(0);
 for(const width of [1366,375]){
  await page.setViewportSize({width,height:900});await page.goto('/qa-admin.html');
  await expect(page.getByRole('heading',{name:'Administración del sistema'})).toBeVisible();
  await expect(page.locator('form[action$="/administracion/ingreso"]')).toHaveCount(1);
  await expect(page.locator('a[href$="/registro"]')).toHaveCount(0);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth)).toBe(true);
  await page.screenshot({path:testInfo.outputPath(`administracion-${width}.png`),fullPage:true});
 }
});

test('pago simulado sin tarjeta obligatoria y con código de cuatro dígitos',async({page},testInfo)=>{
 await page.goto('/qa-payment.html');
 await expect(page.getByText('4242 4242 4242 4242',{exact:false})).toHaveCount(0);
 await expect(page.getByText('No se realizará ningún cargo.',{exact:false})).toBeVisible();
 for(const width of [1366,375]){
  await page.setViewportSize({width,height:900});
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth)).toBe(true);
  await page.screenshot({path:testInfo.outputPath(`pago-${width}.png`),fullPage:true});
 }
 await page.getByLabel('Nombre del titular').fill('Docente de prueba');
 await page.getByLabel('Correo de facturación').fill('prueba@example.test');
 await page.getByLabel('Número de tarjeta',{exact:true}).fill('4111111111111111');
 await page.getByLabel('Código de seguridad (CVV/CID)',{exact:true}).fill('9876');
 expect(await page.getByLabel('Código de seguridad (CVV/CID)',{exact:true}).evaluate(input=>input.checkValidity())).toBe(false);
 await page.getByLabel('Número de tarjeta',{exact:true}).fill('378282246310005');
 await page.getByLabel('Vencimiento (MM/AA)',{exact:true}).fill('12/39');
 await page.getByLabel('Código de seguridad (CVV/CID)',{exact:true}).fill('9876');
 await page.route('**/suscripcion/planes/pago/simular',async route=>{
  const data=new URLSearchParams(route.request().postData());
  expect(data.get('numero_tarjeta')).toBe('378282246310005');expect(data.get('cvv')).toBe('9876');
  await route.fulfill({contentType:'text/html',body:'<h1>Plan activado exitosamente</h1>'});
 });
 await page.getByRole('button',{name:'Activar plan',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Plan activado exitosamente'})).toBeVisible();
});
