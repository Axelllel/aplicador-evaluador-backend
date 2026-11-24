import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';

const html = mode => readFile(`public/qa-recovery-${mode}.html`, 'utf8');

test('recuperación docente con correo completo, pista de dominio y confirmación', async ({page}, testInfo) => {
 await page.goto('/qa-login.html');
 await expect(page.getByRole('link',{name:'¿Olvidaste tu contraseña?'})).toHaveAttribute('href',/\/recuperar-contrasena$/);
 await page.route('**/recuperar-contrasena', async route => {
  const submitted=route.request().method()==='POST';
  if(submitted)expect(new URLSearchParams(route.request().postData()).get('email')).toBe('docente@outlook.com');
  await route.fulfill({contentType:'text/html; charset=utf-8',body:await html(submitted?'confirmar':'correo')});
 });
 await page.getByRole('link',{name:'¿Olvidaste tu contraseña?'}).click();
 await page.getByLabel('Correo electrónico registrado',{exact:true}).fill('docente@outlook.com');
 await page.getByRole('button',{name:'Continuar',exact:true}).click();
 await expect(page.getByText('…@outlook.com',{exact:true})).toBeVisible();
 await expect(page.locator('input[type=email]')).toHaveValue('');
 await page.route('**/recuperar-contrasena/confirmar', async route => {
  expect(new URLSearchParams(route.request().postData()).get('email_confirmation')).toBe('docente@outlook.com');
  await route.fulfill({contentType:'text/html; charset=utf-8',body:await html('enviado')});
 });
 for(const width of [1366,375]){
  await page.setViewportSize({width,height:900});
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth)).toBe(true);
  await page.screenshot({path:testInfo.outputPath(`confirmar-correo-${width}.png`),fullPage:true,animations:'disabled'});
 }
 await page.getByLabel('Escribe otra vez tu correo completo',{exact:true}).fill('docente@outlook.com');
 await page.getByRole('button',{name:'Enviar enlace de recuperación',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Revisa tu correo'})).toBeVisible();
 await expect(page.getByText('Si el correo corresponde a una cuenta registrada',{exact:false})).toBeVisible();
});

test('el enlace se limpia de la URL y el cambio solo envía la contraseña al servidor', async ({page}) => {
 let verified=0,changed=0;
 await page.route('**/restablecer-contrasena', async route => {
  if(route.request().method()==='POST'){
   changed++;
   const data=new URLSearchParams(route.request().postData());
   expect(data.get('password')).toBe('clave-segura-nueva');expect(data.has('access_token')).toBe(false);
   await route.fulfill({contentType:'text/html; charset=utf-8',body:'<h1>Contraseña actualizada</h1>'});
  }else await route.fulfill({contentType:'text/html; charset=utf-8',body:await html('enlace')});
 });
 await page.route('**/restablecer-contrasena/verificar', async route => {
  verified++;
  expect(new URLSearchParams(route.request().postData()).get('access_token')).toBe('prueba-secreta');
  expect(route.request().url()).not.toContain('prueba-secreta');
  expect(route.request().headers()['referer'] || '').not.toContain('prueba-secreta');
  await route.fulfill({contentType:'text/html; charset=utf-8',body:await html('restablecer')});
 });
 await page.goto('/restablecer-contrasena#access_token=prueba-secreta&refresh_token=otro-secreto&type=recovery');
 await expect(page.getByRole('heading',{name:'Crea una nueva contraseña'})).toBeVisible();
 expect(verified).toBe(1);
 expect(page.url()).not.toContain('prueba-secreta');
 expect(await page.evaluate(()=>JSON.stringify(localStorage))).not.toContain('secreta');
 await page.getByLabel('Nueva contraseña',{exact:true}).fill('clave-segura-nueva');
 await page.getByLabel('Confirma la nueva contraseña',{exact:true}).fill('clave-segura-nueva');
 await page.getByRole('button',{name:'Guardar nueva contraseña',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Contraseña actualizada'})).toBeVisible();
 expect(changed).toBe(1);
});
