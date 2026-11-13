#!/usr/bin/env python3
"""Kontrola interfejsu przez CDP z lokalnym Chrome; używa zastanego websockets."""
import asyncio,base64,json,pathlib,urllib.request,secrets,os
if os.environ.get("ARKUSZ_ALLOW_TEST_DATA")!="1" or not os.environ.get("ARKUSZ_TEST_URL"):raise SystemExit("Uruchom test na osobnej instalacji z ARKUSZ_ALLOW_TEST_DATA=1 i ARKUSZ_TEST_URL.")
import websockets
async def main():
 pages=json.load(urllib.request.urlopen('http://127.0.0.1:9223/json'))
 page=next(p for p in pages if p['type']=='page')
 async with websockets.connect(page['webSocketDebuggerUrl'],max_size=10000000) as ws:
  seq=0;errors=[]
  async def cmd(method,params={}):
   nonlocal seq
   seq+=1;i=seq;await ws.send(json.dumps({'id':i,'method':method,'params':params}))
   while True:
    r=json.loads(await ws.recv())
    if r.get('method')=='Runtime.exceptionThrown':errors.append(r['params'])
    if r.get('id')==i:
     if 'error'in r:raise RuntimeError(r['error'])
     return r.get('result',{})
  async def js(expr):
   r=await cmd('Runtime.evaluate',{'expression':expr,'awaitPromise':True,'returnByValue':True})
   if 'exceptionDetails'in r:raise RuntimeError(r['exceptionDetails'])
   return r['result'].get('value')
  async def wait(expr):
   for _ in range(100):
    if await js(expr):return
    await asyncio.sleep(.1)
   raise RuntimeError('Timeout: '+expr)
  await cmd('Runtime.enable');await cmd('Page.enable');await cmd('Network.clearBrowserCookies')
  await cmd('Emulation.setDeviceMetricsOverride',{'width':1440,'height':1000,'deviceScaleFactor':1,'mobile':False})
  await cmd('Page.navigate',{'url':os.environ.get('ARKUSZ_TEST_URL','http://localhost:8080')+'/'})
  await wait("document.querySelector('#login-view') && !document.querySelector('#login-view').hidden")
  env=dict(line.split('=',1) for line in (pathlib.Path('.env').read_text() if pathlib.Path('.env').exists() else 'ADMIN_PASSWORD=').splitlines())
  await js("document.querySelector('[name=login]').value='admin';document.querySelector('[name=password]').value="+json.dumps(os.environ.get('ARKUSZ_TEST_PASSWORD',env['ADMIN_PASSWORD']))+";document.querySelector('#login-form').requestSubmit();")
  await wait("!document.querySelector('#app').hidden && !!document.querySelector('#new-order')")
  assert await js("document.querySelectorAll('tbody tr').length")>=1
  shot=await cmd('Page.captureScreenshot',{'format':'png','captureBeyondViewport':False});pathlib.Path('/tmp/arkusz-desktop.png').write_bytes(base64.b64decode(shot['data']))
  await js("document.querySelector('#new-order').click()")
  await wait("document.querySelector('#modal').open")
  await js("document.querySelector('[data-add-component]').click();document.querySelector('[data-add-component-feature]').click();")
  assert await js("document.querySelectorAll('[data-component]').length") == 2
  assert await js("document.querySelectorAll('[data-param]').length") == 4
  await wait("!!document.querySelector('#customer-select option[value]:not([value=\"\"])') && !!document.querySelector('#customer-select').value")
  # Przejście przez prawdziwy formularz nowego zamówienia.
  await js("document.querySelector('[name=title]').value='TEST Chrome '+"+json.dumps(secrets.token_hex(3))+";document.querySelector('[data-item-key=name]').value='Wizytówki z formularza';document.querySelector('[data-param=kind]').value='Tłoczenie wypukłe';document.querySelector('#order-form').requestSubmit();")
  await wait("!document.querySelector('#modal').open && location.hash.startsWith('#order/') && !!document.querySelector('[data-new-task]')")
  assert await js("document.querySelectorAll('#content .component-box').length") == 2
  # Edycja nagłówka, produktu i części na tym samym zamówieniu.
  await js("document.querySelector('#edit-order').click()")
  await wait("document.querySelector('#modal').open && !!document.querySelector('#order-form')")
  assert await js("document.querySelectorAll('#order-form [data-component]').length") == 2
  assert await js("document.querySelector('#order-form [data-param=kind]').value") == 'Tłoczenie wypukłe'
  await js("document.querySelector('#order-form [name=customer_notes]').value='Informacje do zamówienia z Chrome\\nDruga linia';document.querySelector('#order-form [name=internal_notes]').value='Notatka dla zespołu';document.querySelector('#order-form [data-item-key=specification_notes]').value='Informacje do produktu';document.querySelector('#order-form [data-key=notes]').value='Informacje do części — ąćęłńóśźż';document.querySelector('#order-form [data-key=print_technique]').value='offset';document.querySelector('#order-form [data-key=print_technique]').dispatchEvent(new Event('change'));document.querySelector('#order-form [data-key=offset_front_colors]').value=1;document.querySelector('#order-form [data-key=offset_front_colors]').dispatchEvent(new Event('change'));document.querySelector('#order-form [data-key=offset_back_colors]').value=0;document.querySelector('#order-form [data-key=offset_back_colors]').dispatchEvent(new Event('change'));document.querySelector('#order-form [data-ink=front]').value='Pantone 185';document.querySelector('#order-form').requestSubmit();")
  await wait("!!document.querySelector('#confirm-order-plan')")
  assert await js("document.querySelector('[data-order-fields]').disabled && !document.querySelector('#order-plan-preview').hidden")
  assert await js("!document.querySelector('#content').textContent.includes('Informacje do zamówienia z Chrome')")
  await js("document.querySelector('#back-to-order').click()")
  assert await js("!document.querySelector('[data-order-fields]').disabled && document.querySelector('#order-plan-preview').hidden")
  await js("document.querySelector('#order-form').requestSubmit()")
  await wait("!document.querySelector('#order-plan-preview').hidden")
  await js("document.querySelector('#confirm-order-plan').click()")
  await wait("!document.querySelector('#modal').open && document.querySelector('#content').textContent.includes('Informacje do części — ąćęłńóśźż')")
  assert await js("document.querySelector('#content').textContent.includes('Informacje do zamówienia z Chrome') && document.querySelector('#content').textContent.includes('Informacje do produktu')")
  await js("document.querySelector('[data-edit-component]').click()")
  await wait("document.querySelector('#modal').open")
  assert await js("document.querySelector('#order-form [data-key=print_technique]').value") == 'offset'
  assert await js("document.querySelector('#order-form [data-ink=front]').value") == 'Pantone 185'
  assert await js("document.querySelector('#order-form [data-key=notes]').value") == 'Informacje do części — ąćęłńóśźż'
  assert await js("document.querySelectorAll('#order-form [data-component]')[1].querySelector('[data-key=notes]').value") == ''
  await js("document.querySelector('#modal').close();document.querySelector('[data-edit-item]').click()")
  await wait("document.querySelector('#modal').open")
  assert await js("document.querySelector('#order-form [data-item-key=specification_notes]').value") == 'Informacje do produktu'
  await js("document.querySelector('#modal').close()")
  shot=await cmd('Page.captureScreenshot',{'format':'png','captureBeyondViewport':False});pathlib.Path('/tmp/arkusz-order-edit.png').write_bytes(base64.b64decode(shot['data']))
  # Podgląd i zatwierdzenie faktycznie zmieniają plan istniejącego zadania.
  await js("(async()=>{window.planTestOrder=currentDetail.order.id;window.planTestItem=currentDetail.items[0].id;window.planTestTask=(await api('create_task',{order_item_id:planTestItem,operation_type_id:state.data.dictionaries.operation_types[0].id,work_center_id:state.data.dictionaries.work_centers[0].id,planned_quantity:500,quantity_unit:'szt.',sequence_number:10})).id;await refresh();document.querySelector('#edit-order').click();document.querySelector('[data-item-key=quantity]').value=750;document.querySelector('#order-form').requestSubmit()})()")
  await wait("!!document.querySelector('#confirm-order-plan')")
  assert await js("(async()=>{return document.querySelector('#order-plan-preview').textContent.includes('500 → 750') && +(await api('tasks')).find(t=>+t.id===+planTestTask).planned_quantity===500})()")
  await cmd('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
  assert await js("document.documentElement.scrollWidth<=window.innerWidth")
  await js("document.querySelector('#confirm-order-plan').click()")
  await wait("!document.querySelector('#modal').open && document.querySelector('#content').textContent.includes('750')")
  assert await js("(async()=>{return +(await api('tasks')).find(t=>+t.id===+planTestTask).planned_quantity===750})()")
  await cmd('Emulation.setDeviceMetricsOverride',{'width':1440,'height':1000,'deviceScaleFactor':1,'mobile':False})
  await js("document.querySelector('#edit-order').click();document.querySelectorAll('[data-remove-component]')[1].click();document.querySelector('#order-form').requestSubmit()")
  await wait("!!document.querySelector('#confirm-order-plan')")
  assert await js("document.querySelector('#order-plan-preview').textContent.includes('anulowane')")
  await js("document.querySelector('#confirm-order-plan').click()")
  await wait("!document.querySelector('#modal').open && document.querySelectorAll('#content .component-box').length===1")
  assert await js("(async()=>{return (await api('tasks')).find(t=>+t.id===+planTestTask).status==='cancelled'})()")
  await js("location.hash='production'")
  await wait("!!document.querySelector('.task-board')")
  await js("location.hash='order/1'")
  await wait("!!document.querySelector('[data-new-task]')")
  await js("document.querySelector('[data-new-task]').click()")
  await wait("!!document.querySelector('#task-form')")
  assert await js("document.querySelectorAll('#task-form [name=depends_on]').length")>=1
  await js("document.querySelector('#modal').close();location.hash='orders'")
  await wait("!!document.querySelector('#order-table')")
  await cmd('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
  await asyncio.sleep(.3)
  assert await js("document.documentElement.scrollWidth<=window.innerWidth")
  shot=await cmd('Page.captureScreenshot',{'format':'png','captureBeyondViewport':False});pathlib.Path('/tmp/arkusz-mobile.png').write_bytes(base64.b64decode(shot['data']))
  # Kategorie słowników i zapis/edycja wpisu przez formularz.
  await cmd('Emulation.setDeviceMetricsOverride',{'width':1440,'height':1000,'deviceScaleFactor':1,'mobile':False})
  await js("document.querySelector('[data-nav=dictionaries]').click()")
  await wait("!!document.querySelector('#add-dictionary')")
  assert await js("document.querySelectorAll('.dictionary-nav a').length") == 10
  await js("document.querySelector('.dictionary-nav a[href=\"#dictionaries/paper_types\"]').click()")
  await wait("document.querySelector('#breadcrumb').textContent==='Słowniki / Rodzaje papieru'")
  name='TEST Chrome papier '+secrets.token_hex(3)
  await js("document.querySelector('#add-dictionary').click();document.querySelector('#dictionary-form [name=name]').value="+json.dumps(name)+";document.querySelector('#dictionary-form').requestSubmit();")
  await wait("!document.querySelector('#modal').open && document.querySelector('#dictionary-rows').textContent.includes("+json.dumps(name)+")")
  await js("document.querySelector('#dictionary-search').value="+json.dumps(name)+";document.querySelector('#dictionary-search').dispatchEvent(new Event('input'));document.querySelector('[data-dictionary-edit]').click();document.querySelector('#dictionary-form [name=name]').value+='/edycja';document.querySelector('#dictionary-form [name=is_active]').checked=false;document.querySelector('#dictionary-form').requestSubmit();")
  await wait("!document.querySelector('#modal').open && document.querySelector('#dictionary-rows').textContent.includes("+json.dumps(name+'/edycja')+")")
  assert await js("[...document.querySelectorAll('#dictionary-rows tr')].find(r=>r.textContent.includes("+json.dumps(name)+")).textContent.includes('Nieaktywny')")
  await js("document.querySelector('.dictionary-nav a[href=\"#dictionaries/roles\"]').click()")
  await wait("document.querySelector('#breadcrumb').textContent==='Słowniki / Role i uprawnienia'")
  await js("document.querySelector('[data-dictionary-edit]').click()")
  assert await js("document.querySelectorAll('#dictionary-form [name=permission_ids]').length") >= 5
  assert await js("document.querySelector('#dictionary-form [name=code]').readOnly")
  await js("document.querySelector('#modal').close()")
  shot=await cmd('Page.captureScreenshot',{'format':'png','captureBeyondViewport':False});pathlib.Path('/tmp/arkusz-dictionaries.png').write_bytes(base64.b64decode(shot['data']))
  await cmd('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
  await asyncio.sleep(.2)
  assert await js("document.documentElement.scrollWidth<=window.innerWidth")
  # Preferencje wyglądu: podgląd, anulowanie, zapis i odtworzenie po odświeżeniu.
  await js("document.querySelector('#about-version').click()")
  await wait("document.querySelector('#modal').open")
  assert await js("document.querySelector('#modal-body').textContent.includes('1.4.0') && /13\\.\\d+\\.\\d+/.test(document.querySelector('#modal-body').textContent) && /8\\.4\\.\\d+/.test(document.querySelector('#modal-body').textContent)")
  await js("document.querySelector('#modal').close()")
  original_theme=await js("state.data.user.theme")
  await cmd('Emulation.setDeviceMetricsOverride',{'width':1440,'height':1000,'deviceScaleFactor':1,'mobile':False})
  for theme in ['beige','beige_light','gray','black','green']:
   await js("document.querySelector('#appearance').click();document.querySelector('#appearance-form [value="+theme+"]').click()")
   assert await js("document.documentElement.dataset.theme") == theme
   if theme=='beige_light':
    assert await js("getComputedStyle(document.querySelector('.sidebar')).backgroundColor") == 'rgb(247, 239, 223)'
    assert await js("document.querySelectorAll('#appearance-form [name=theme]').length") == 5
   await js("document.querySelector('#appearance-form').requestSubmit()")
   await wait("!document.querySelector('#modal').open")
   assert await js("state.data.user.theme") == theme
   await js("load()")
   assert await js("document.documentElement.dataset.theme") == theme
   if theme!='green':
    shot=await cmd('Page.captureScreenshot',{'format':'png','captureBeyondViewport':False});pathlib.Path('/tmp/arkusz-theme-'+theme+'.png').write_bytes(base64.b64decode(shot['data']))
  await js("document.querySelector('#appearance').click();document.querySelector('#appearance-form [value=black]').click();document.querySelector('#modal-close').click()")
  await wait("document.documentElement.dataset.theme==='green'")
  # Sprawdź czytelność i zapis preferencji również w formularzu edycji.
  await js("location.hash='order/1'")
  await wait("!!document.querySelector('#edit-order')")
  await js("document.querySelector('#appearance').click();document.querySelector('#appearance-form [value=black]').click();document.querySelector('#appearance-form').requestSubmit()")
  await wait("!document.querySelector('#modal').open")
  await js("document.querySelector('#edit-order').click()")
  await wait("document.querySelector('#modal').open")
  assert await js("getComputedStyle(document.querySelector('#order-form input')).colorScheme") == 'dark'
  shot=await cmd('Page.captureScreenshot',{'format':'png','captureBeyondViewport':False});pathlib.Path('/tmp/arkusz-theme-black-form.png').write_bytes(base64.b64decode(shot['data']))
  await cmd('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
  assert await js("document.querySelector('#modal').scrollWidth<=document.querySelector('#modal').clientWidth")
  await js("document.querySelector('#modal').close();document.querySelector('#appearance').click();document.querySelector('#appearance-form [value="+original_theme+"]').click();document.querySelector('#appearance-form').requestSubmit()")
  await wait("!document.querySelector('#modal').open")
  manifest=await cmd('Page.getAppManifest');assert not manifest.get('errors'),manifest
  assert not errors,errors
  print('OK: logowanie w Chrome, lista i szczegóły, formularze wielu części i cech, edycja zamówienia/produktu/części i dodatkowych informacji, tablica produkcji, formularz zadań, układ mobilny, edytor słowników (dodawanie/edycja/wyłączanie), role i uprawnienia, motywy per użytkownik (podgląd/anulowanie/zapis/odtworzenie), manifest, brak wyjątków JavaScript.')
asyncio.run(main())
