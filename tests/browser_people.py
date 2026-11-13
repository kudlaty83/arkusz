#!/usr/bin/env python3
"""Formularze klientów i kont oraz stronicowanie w prawdziwej przeglądarce."""
import asyncio,base64,json,os,pathlib,secrets,urllib.request
if os.environ.get('ARKUSZ_ALLOW_TEST_DATA')!='1' or not os.environ.get('ARKUSZ_TEST_URL'):raise SystemExit('Wymagana osobna instalacja testowa i ARKUSZ_ALLOW_TEST_DATA=1.')
import websockets
async def main():
 page=next(p for p in json.load(urllib.request.urlopen('http://127.0.0.1:9223/json')) if p['type']=='page')
 async with websockets.connect(page['webSocketDebuggerUrl'],max_size=10000000) as ws:
  seq=0;errors=[]
  async def cmd(method,params={}):
   nonlocal seq
   seq+=1;id=seq;await ws.send(json.dumps({'id':id,'method':method,'params':params}))
   while True:
    r=json.loads(await ws.recv())
    if r.get('method')=='Runtime.exceptionThrown':errors.append(r['params'])
    if r.get('id')==id:
     if 'error' in r:raise RuntimeError(r['error'])
     return r.get('result',{})
  async def js(expr):
   r=await cmd('Runtime.evaluate',{'expression':expr,'awaitPromise':True,'returnByValue':True})
   if 'exceptionDetails' in r:raise RuntimeError(r['exceptionDetails'])
   return r['result'].get('value')
  async def wait(expr):
   for _ in range(120):
    if await js(expr):return
    await asyncio.sleep(.1)
   raise RuntimeError('Timeout: '+expr+'; '+str(await js("document.querySelector('.error')?.textContent")))
  async def login(login,password):
   await js("document.querySelector('[name=login]').value="+json.dumps(login)+";document.querySelector('[name=password]').value="+json.dumps(password)+";document.querySelector('#login-form').requestSubmit()")
   await wait("!document.querySelector('#app').hidden && !!state.data")
  async def search(value):
   await js("document.querySelector('#people-search').value="+json.dumps(value)+";document.querySelector('#people-search').dispatchEvent(new Event('input'))")
   await asyncio.sleep(.4);await wait("document.querySelector('#people-results')?.getAttribute('aria-busy')==='false'")
  await cmd('Runtime.enable');await cmd('Page.enable');await cmd('Network.clearBrowserCookies')
  await cmd('Emulation.setDeviceMetricsOverride',{'width':1440,'height':1000,'deviceScaleFactor':1,'mobile':False})
  await cmd('Page.navigate',{'url':os.environ['ARKUSZ_TEST_URL']+'/'})
  await wait("document.querySelector('#login-view') && !document.querySelector('#login-view').hidden")
  admin_password=os.environ['ARKUSZ_TEST_PASSWORD'];await login('admin',admin_password)
  await js("location.hash='customers'");await wait("!!document.querySelector('#new-customer') && document.querySelector('#people-results').getAttribute('aria-busy')==='false'")
  tag=secrets.token_hex(4);prefix='TEST Browser '+tag+' '
  await js("window.browserPrefix="+json.dumps(prefix)+";document.querySelector('#new-customer').click();document.querySelector('#customer-form [name=name]').value=browserPrefix+'001';document.querySelector('#customer-form [name=email]').value='browser@example.test';document.querySelector('#customer-form [name=notes]').value='Pierwsze uwagi';document.querySelector('#customer-form').requestSubmit()")
  await wait("!document.querySelector('#modal').open");await search(prefix)
  assert await js("document.querySelectorAll('#people-results tbody tr').length===1")
  await js("window.browserCustomer=+document.querySelector('[data-edit-customer]').dataset.editCustomer;document.querySelector('[data-edit-customer]').click()")
  await wait("document.querySelector('#modal').open && !!document.querySelector('#customer-form')")
  assert await js("document.querySelector('#customer-form [name=notes]').value==='Pierwsze uwagi'")
  await js("document.querySelector('#customer-form [name=name]').value+=' edycja';document.querySelector('#customer-form [name=address_text]').value='Adres — ąćęłńóśźż';document.querySelector('#customer-form [name=is_active]').checked=false;document.querySelector('#customer-form').requestSubmit()")
  await wait("!document.querySelector('#modal').open && document.querySelector('#people-results').textContent.includes('Brak klientów')")
  await js("document.querySelector('#people-active').value='0';document.querySelector('#people-active').dispatchEvent(new Event('change'))")
  await wait("!!document.querySelector('[data-edit-customer]')");await js("document.querySelector('[data-edit-customer]').click()")
  await wait("document.querySelector('#modal').open && !!document.querySelector('#customer-form')");assert await js("document.querySelector('#customer-form [name=address_text]').value==='Adres — ąćęłńóśźż'")
  await js("document.querySelector('#customer-form [name=is_active]').checked=true;document.querySelector('#customer-form').requestSubmit()")
  await wait("!document.querySelector('#modal').open")
  await js("(async()=>{for(let n=2;n<=27;n++)await api('customer',{name:browserPrefix+String(n).padStart(3,'0'),email:'browser-'+n+'@example.test'});document.querySelector('#people-active').value='1';document.querySelector('#people-active').dispatchEvent(new Event('change'));document.querySelector('#people-size').value='10';document.querySelector('#people-size').dispatchEvent(new Event('change'));})()")
  await wait("document.querySelectorAll('#people-results tbody tr').length===10 && document.querySelector('.pagination').textContent.includes('27 klientów')")
  await js("document.querySelector('[data-page=\"2\"]').click()")
  await wait("document.querySelector('.pagination').textContent.includes('Strona 2 z 3')")
  await js("document.querySelector('[data-page=\"3\"]').click()")
  await wait("document.querySelectorAll('#people-results tbody tr').length===7")
  shot=await cmd('Page.captureScreenshot',{'format':'png'});pathlib.Path('/tmp/arkusz-customers.png').write_bytes(base64.b64decode(shot['data']))
  # Wybór klienta spoza pierwszej strony w zamówieniu.
  await js("location.hash='orders'");await wait("!!document.querySelector('#new-order')");await js("document.querySelector('#new-order').click()")
  await wait("!!document.querySelector('#customer-select').value")
  await js("document.querySelector('#customer-search').value=browserPrefix;document.querySelector('#customer-find').click()")
  await wait("document.querySelector('#customer-page-info').textContent.includes('27 wyników')")
  await js("document.querySelector('#customer-next').click()")
  await wait("document.querySelector('#customer-page-info').textContent.includes('strona 2 z 3')")
  await js("document.querySelector('#customer-next').click()")
  await wait("document.querySelector('#customer-page-info').textContent.includes('strona 3 z 3')")
  assert await js("document.querySelectorAll('#customer-select option').length<=12")
  await js("document.querySelector('#customer-select').value=[...document.querySelector('#customer-select').options].find(o=>o.text===browserPrefix+'027').value;document.querySelector('#customer-select').dispatchEvent(new Event('change'));document.querySelector('#order-form [name=title]').value='TEST wybór klienta';document.querySelector('[data-item-key=name]').value='Wizytówki';document.querySelector('#order-form').requestSubmit()")
  await wait("!document.querySelector('#modal').open && !!document.querySelector('#edit-order')")
  assert await js("currentDetail.order.customer_name===browserPrefix+'027'")
  await js("document.querySelector('#edit-order').click()")
  await wait("document.querySelector('#customer-page-info').textContent.includes('strona 1')")
  assert await js("+document.querySelector('#customer-select').value===+currentDetail.order.customer_id")
  await js("document.querySelector('#modal').close();location.hash='team'")
  await wait("!!document.querySelector('#new-user')")
  password=secrets.token_urlsafe(18);reset=secrets.token_urlsafe(18);final_password=secrets.token_urlsafe(18);login_name='browser-'+tag
  await js("document.querySelector('#new-user').click();document.querySelector('#user-form [name=display_name]').value="+json.dumps(prefix+'Worker')+";document.querySelector('#user-form [name=login]').value="+json.dumps(login_name)+";document.querySelector('#user-form [name=password]').value="+json.dumps(password)+";document.querySelector('#user-form [name=role_ids][value=\"'+state.data.dictionaries.roles.find(r=>r.code==='graphic').id+'\"]').checked=true;document.querySelector('#user-form').requestSubmit()")
  await wait("!document.querySelector('#modal').open");await search(prefix+'Worker')
  await js("window.browserUser=+document.querySelector('[data-edit-user]').dataset.editUser;document.querySelector('[data-edit-user]').click()")
  await wait("document.querySelector('#modal').open && !!document.querySelector('#user-form')")
  await js("document.querySelector('#user-form [name=display_name]').value+=' edycja';document.querySelector('#user-form [name=work_center_ids]').checked=true;document.querySelector('#user-form').requestSubmit()")
  await wait("!document.querySelector('#modal').open && document.querySelector('#people-results').textContent.includes('Worker edycja')")
  await js("document.querySelector('[data-reset-password]').click()")
  await wait("document.querySelector('#modal').open && !!document.querySelector('#password-form')")
  await js("document.querySelector('#password-form [name=password]').value="+json.dumps(reset)+";document.querySelector('#password-form [name=password_confirmation]').value="+json.dumps(reset)+";document.querySelector('#password-form').requestSubmit()")
  await wait("!document.querySelector('#modal').open")
  await js("document.querySelector('#logout').click()")
  await wait("!document.querySelector('#login-view').hidden");await login(login_name,reset)
  await js("document.querySelector('#change-password').click()")
  await wait("document.querySelector('#modal').open && !!document.querySelector('#password-form')")
  await js("document.querySelector('#password-form [name=current_password]').value='Niepoprawne hasło';document.querySelector('#password-form [name=password]').value="+json.dumps(final_password)+";document.querySelector('#password-form [name=password_confirmation]').value="+json.dumps(final_password)+";document.querySelector('#password-form').requestSubmit()")
  await wait("document.querySelector('#password-form .error').textContent.includes('nieprawidłowe')")
  await js("document.querySelector('#password-form [name=current_password]').value="+json.dumps(reset)+";document.querySelector('#password-form').requestSubmit()")
  await wait("!document.querySelector('#modal').open")
  assert await js("(async()=>{return (await api('bootstrap')).user.id===browserUser})()")
  await cmd('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
  await js("location.hash='customers'");await wait("!!document.querySelector('#people-search')")
  assert await js("document.documentElement.scrollWidth<=window.innerWidth")
  await js("document.querySelector('#change-password').click()")
  assert await js("document.documentElement.scrollWidth<=window.innerWidth")
  await js("document.querySelector('#modal').close();document.querySelector('#logout').click()")
  await wait("!document.querySelector('#login-view').hidden");await login('admin',admin_password)
  assert not errors,errors
  print('OK: Chrome — klient (dodawanie/edycja/wyłączenie), strony listy i wyboru w zamówieniu, konto i stanowiska, reset hasła, własne hasło, błędy walidacji oraz układ mobilny bez wyjątków JS.')
asyncio.run(main())
