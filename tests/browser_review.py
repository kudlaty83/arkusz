#!/usr/bin/env python3
"""Anulowanie i reguły ilości przez Chrome/CDP na osobnej instalacji."""
import asyncio,base64,json,os,pathlib,secrets,urllib.request
if os.environ.get('ARKUSZ_ALLOW_TEST_DATA')!='1' or not os.environ.get('ARKUSZ_TEST_URL'):raise SystemExit('Wymagana osobna instalacja testowa.')
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
   for _ in range(150):
    if await js(expr):return
    await asyncio.sleep(.1)
   raise RuntimeError('Timeout: '+expr+'; '+str(await js("document.querySelector('.error')?.textContent")))
  await cmd('Runtime.enable');await cmd('Page.enable');await cmd('Network.clearBrowserCookies')
  await cmd('Emulation.setDeviceMetricsOverride',{'width':1440,'height':1000,'deviceScaleFactor':1,'mobile':False})
  await cmd('Page.navigate',{'url':os.environ['ARKUSZ_TEST_URL']+'/'})
  await wait("document.querySelector('#login-view') && !document.querySelector('#login-view').hidden")
  await js("document.querySelector('[name=login]').value='admin';document.querySelector('[name=password]').value="+json.dumps(os.environ['ARKUSZ_TEST_PASSWORD'])+";document.querySelector('#login-form').requestSubmit()")
  await wait("!document.querySelector('#app').hidden")
  await js("location.hash='orders'");await wait("!!document.querySelector('#new-order')");await js("document.querySelector('#new-order').click()")
  await wait("!!document.querySelector('#customer-select').value")
  await js("document.querySelector('#order-form [name=title]').value='TEST Chrome anulowanie';document.querySelector('[data-item-key=name]').value='TEST Wizytówki';document.querySelector('[data-item-key=quantity]').value=10;document.querySelector('#order-form').requestSubmit()")
  await wait("!document.querySelector('#modal').open && !!document.querySelector('[data-new-task]')")
  await js("(async()=>{window.fixOrder=currentDetail.order.id;const i=currentDetail.items[0];const base={order_item_id:i.id,component_id:i.components[0].id,operation_type_id:state.data.dictionaries.operation_types[0].id,work_center_id:state.data.dictionaries.work_centers[0].id,planned_quantity:10,quantity_unit:'szt.',sequence_number:10,assignee_ids:[state.data.user.id]};window.fixFirst=(await api('create_task',base)).id;window.fixSecond=(await api('create_task',{...base,depends_on:[fixFirst]})).id;window.fixThird=(await api('create_task',base)).id;await refresh();})()")
  await js("document.querySelector('[data-edit-task=\"'+fixThird+'\"]').click()")
  await wait("!!document.querySelector('#task-form')")
  assert await js("document.querySelector('[name=quantity_mode]').value==='per_copy' && document.querySelector('#task-form').textContent.includes('Zapisany współczynnik')")
  await js("document.querySelector('[name=quantity_mode]').value='fixed';document.querySelector('#task-form').requestSubmit()")
  await wait("!!document.querySelector('[data-accept-plan]')")
  assert await js("document.querySelector('[data-confirm-plan]').textContent.includes('sposób liczenia')")
  await js("document.querySelector('[data-accept-plan]').click()")
  await wait("!document.querySelector('#modal').open")
  assert await js("(async()=>{return (await api('task&id='+fixThird)).quantity_mode==='fixed'})()")
  await js("document.querySelector('[data-report=\"'+fixFirst+'\"]').click();document.querySelector('#report-form [name=status_after]').value='cancelled';document.querySelector('#report-form [name=notes]').value='TEST anulowanie gałęzi';document.querySelector('#report-form').requestSubmit()")
  await wait("!!document.querySelector('[data-accept-plan]')")
  assert await js("document.querySelector('[data-confirm-plan]').textContent.includes('#'+fixSecond) && state.tasks.find(t=>+t.id===+fixFirst).status!=='cancelled'")
  await js("document.querySelector('[data-back-plan]').click()")
  assert await js("!document.querySelector('[data-plan-fields]').disabled")
  await js("document.querySelector('#report-form').requestSubmit()")
  await wait("!document.querySelector('[data-confirm-plan]').hidden")
  await js("document.querySelector('[data-accept-plan]').click()")
  await wait("!document.querySelector('#modal').open && state.tasks.find(t=>+t.id===+fixSecond).status==='cancelled'")
  await js("document.querySelector('#status-select').value=state.data.dictionaries.order_statuses.find(s=>s.code==='cancelled').id;document.querySelector('#change-status').click()")
  await wait("!!document.querySelector('#cancel-order-form')")
  await js("document.querySelector('#cancel-order-form [name=reason]').value='TEST rezygnacja';document.querySelector('#cancel-order-form').requestSubmit()")
  await wait("!!document.querySelector('[data-accept-plan]')")
  assert await js("document.querySelector('[data-confirm-plan]').textContent.includes('#'+fixThird) && state.tasks.find(t=>+t.id===+fixThird).status!=='cancelled'")
  await cmd('Emulation.setDeviceMetricsOverride',{'width':390,'height':844,'deviceScaleFactor':1,'mobile':True})
  assert await js("document.documentElement.scrollWidth<=window.innerWidth")
  await js("document.querySelector('[data-accept-plan]').click()")
  await wait("!document.querySelector('#modal').open && state.tasks.find(t=>+t.id===+fixThird).status==='cancelled'")
  assert await js("!document.querySelector('[data-report]') && !document.querySelector('[data-assign]')")
  assert not errors,errors
  print('OK: Chrome — wybór reguły ilości, podgląd i potwierdzenie anulowania etapu z następnikami, powrót do edycji, anulowanie zamówienia i układ mobilny.')
asyncio.run(main())
