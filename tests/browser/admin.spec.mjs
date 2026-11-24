import { test, expect } from '@playwright/test';

test('panel administrativo con indicadores, temas y navegación adaptable',async({page},testInfo)=>{
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 await page.goto('/qa-admin-dashboard.html');
 await expect(page.getByRole('heading',{name:'Resumen general'})).toBeVisible();
 await expect(page.locator('.ad-metric')).toHaveCount(4);
 await expect(page.locator('.ad-chart-bar')).toHaveCount(6);
 await expect(page.locator('a[href$="/grupos"]')).toHaveCount(0);
 for(const width of [1440,1024,768,375]){
  await page.setViewportSize({width,height:1000});
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth)).toBe(true);
  if(width<1100){
   const menu=page.getByRole('button',{name:'Abrir menú administrativo'});
   await menu.click();
   await expect(page.locator('#admin-sidebar')).toBeInViewport();
   await expect(page.locator('#admin-workspace')).toHaveAttribute('inert','');
   await page.keyboard.press('Escape');
   await expect(menu).toBeFocused();
   await expect(menu).toHaveAttribute('aria-expanded','false');
  }
  await page.screenshot({path:testInfo.outputPath(`admin-resumen-${width}.png`),fullPage:true});
 }
 await page.setViewportSize({width:1440,height:1000});
 await page.getByRole('button',{name:'Activar modo oscuro'}).click();
 await expect(page.locator('[data-admin-shell]')).toHaveClass(/tp-dark/);
 await page.screenshot({path:testInfo.outputPath('admin-resumen-oscuro.png'),fullPage:true});
 await page.reload();
 await expect(page.getByRole('button',{name:'Activar modo claro'})).toBeVisible();
 expect(errors).toEqual([]);
});

test('la gestión se abre en un diálogo y exige un motivo antes de cambiar el estado',async({page},testInfo)=>{
 await page.goto('/qa-admin-teachers.html');
 await expect(page.locator('.ad-table tbody tr')).toHaveCount(15);
 await page.locator('[data-modal-abrir]').first().click();
 const dialog=page.locator('dialog[open]');
 await expect(dialog).toBeVisible();
 await expect(dialog.getByRole('textbox',{name:'Motivo del cambio'})).toBeVisible();
 await page.setViewportSize({width:375,height:900});
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth)).toBe(true);
 await page.screenshot({path:testInfo.outputPath('admin-docente-dialogo-movil.png'),fullPage:true});
 await page.keyboard.press('Escape');
 await expect(page.locator('dialog[open]')).toHaveCount(0);
 await page.goto('/qa-admin-payments.html');
 await page.locator('[data-modal-abrir]').first().click();
 await expect(dialog.getByRole('combobox')).toHaveValue('confirmado');
 await dialog.getByRole('combobox').selectOption('rechazado');
 await dialog.getByRole('button',{name:'Guardar',exact:true}).click();
 await expect(dialog).toBeVisible();
 const target=await dialog.getAttribute('id');
 await page.route('**/administracion/pagos/*',async route=>{
  expect(route.request().url()).toContain('/pagos/'+target.replace('pago-',''));
  const data=new URLSearchParams(route.request().postData());
  expect(data.get('estado')).toBe('rechazado');expect(data.get('motivo')).toBe('Verificado por administración');
  await route.fulfill({contentType:'text/html',body:'<h1>Estado actualizado</h1>'});
 });
 await dialog.getByRole('textbox',{name:'Motivo del cambio'}).fill('Verificado por administración');
 await page.screenshot({path:testInfo.outputPath('admin-pago-dialogo-movil.png'),fullPage:true});
 await dialog.getByRole('button',{name:'Guardar',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Estado actualizado'})).toBeVisible();
});

test('el login administrativo conserva el diseño docente y su formulario separado',async({page},testInfo)=>{
 await page.goto('/qa-admin.html');
 await expect(page.locator('.entry-card')).toBeVisible();
 await expect(page.locator('form[action$="/administracion/ingreso"]')).toHaveCount(1);
 await expect(page.locator('form[action$="/login"]')).toHaveCount(0);
 await page.getByRole('button',{name:'Activar modo oscuro'}).click();
 await expect(page.locator('[data-admin-login]')).toHaveClass(/dark/);
 await page.screenshot({path:testInfo.outputPath('admin-login-oscuro.png'),fullPage:true});
});
