#!/usr/bin/env python3
"""Regresje anulowania, ilości, limitu logowania i zakresu sekretów."""
import argparse,concurrent.futures,copy,hashlib,json,os,pathlib,secrets,shlex,subprocess,threading,urllib.error,urllib.request
p=argparse.ArgumentParser();p.add_argument('--engine',default='podman compose');p.add_argument('--project',default='arkusz-fixes-test');args=p.parse_args()
if args.project=='arkusz':raise SystemExit('Wymagana osobna instalacja.')
command=shlex.split(args.engine)+['-p',args.project]
def compose(*parts):return subprocess.check_output(command+list(parts),stderr=subprocess.PIPE)
config=json.loads(compose('config','--format','json'));root=pathlib.Path(config['services']['setup']['environment']['APP_DATA_HOST_PATH'])
if root.resolve()==(pathlib.Path.cwd()/'app_data').resolve():raise SystemExit('Nie wykonuj testu na instalacji użytkowej.')
from smoke import admin,b,d,c,item,Client,task,report,uid

def php(code):return compose('exec','-T','web','php','-r',code)
def sql(query):return php('require "/var/www/db.php";db()->exec('+json.dumps(query,ensure_ascii=False)+');')
def data_order(id):
 detail=admin.call('order&id='+str(id));o=detail['order'];data={k:o[k] for k in ['id','version','customer_id','title','priority','contact_name','contact_email','contact_phone','customer_notes','internal_notes']};data['due_at']=o['due_at'].replace(' ','T')[:16] if o['due_at'] else '';data['items']=detail['items']
 for i in data['items']:
  features=i.pop('features')
  for f in features:f['values']={v['code']:v['value_text'] if v['value_text'] is not None else v['value_number'] if v['value_number'] is not None else v['value_boolean'] for v in f['values']}
  i['features']=[f for f in features if f['scope']=='item']
  for comp in i['components']:
   comp['features']=[f for f in features if f['scope']=='component' and f['component_id']==comp['id']]
   for side in ['front','back']:comp[side+'_inks']=[k['ink_name'] for k in comp['inks'] if k['side']==side]
 return data

def copies(id,n):
 data=data_order(id);data['items'][0]['quantity']=n;preview=admin.call('preview_order_update',data);return admin.call('update_order',{**data,'confirmation_token':preview['confirmation_token']})
def new_order():return admin.call('create_order',{'customer_id':c,'title':'TEST poprawność '+secrets.token_hex(4),'items':[copy.deepcopy(item)]})['id']
def add(order,qty=10,deps=None):
 it=admin.call('order&id='+str(order))['items'][0]
 return admin.call('create_task',{'order_item_id':it['id'],'component_id':it['components'][0]['id'],'operation_type_id':d['operation_types'][0]['id'],'work_center_id':d['work_centers'][0]['id'],'planned_quantity':qty,'quantity_unit':'szt.','sequence_number':10,'depends_on':deps or [],'assignee_ids':[uid]})['id']
def report_data(id,status):return {'task_id':id,'version':task(id)['version'],'status_after':status,'good_quantity':0,'waste_quantity':0,'work_minutes':0,'notes':'TEST anulowanie'}
# Stabilna baza dla ręcznie zaplanowanej ilości, bez kolejnych zaokrągleń.
o=new_order();a=add(o,13);copies(o,11);assert task(a)['planned_quantity']==15;copies(o,10);assert task(a)['planned_quantity']==13
for n in [19,7,23,11,10]:copies(o,n)
assert task(a)['planned_quantity']==13
# Stała ilość z szablonu pozostaje stała; współczynnik pochodzi z użytej wersji.
kind=next(k['id'] for k in d['component_types'] if k['code']=='cover');draft=copy.deepcopy(item);draft['components'][0]['component_type_id']=kind
o=admin.call('create_order',{'customer_id':c,'title':'TEST ilości szablonu','items':[draft]})['id'];component=admin.call('order&id='+str(o))['items'][0]['components'][0]
step={'operation_type_id':d['operation_types'][0]['id'],'work_center_id':d['work_centers'][0]['id'],'quantity_mode':'fixed','quantity_value':3,'quantity_unit':'próby','days_before_due':0,'depends_on_previous':0}
template=admin.call('save_template',{'name':'TEST baza '+secrets.token_hex(4),'component_type_id':kind,'steps':[step,{**step,'quantity_mode':'per_copy','quantity_value':1.25,'quantity_unit':'ark.'}]})['id']
data={'component_id':component['id'],'template_id':template};v=admin.call('preview_apply_template',data);fixed,proportional=admin.call('apply_template',{**data,'confirmation_token':v['confirmation_token']})['task_ids']
report(fixed,'in_progress');report(fixed,'done',3);copies(o,20);assert task(fixed)['planned_quantity']==3 and task(fixed)['status']=='done';assert task(proportional)['planned_quantity']==25
copies(o,10);assert task(proportional)['planned_quantity']==13
current=admin.call('template&id='+str(template));current['steps'][1]['quantity_value']=9;admin.call('save_template',current);copies(o,11);assert task(proportional)['planned_quantity']==14
# Weryfikacja następników po ilościowym otwarciu poprzednika, także przy nadprodukcji.
o=new_order();a=add(o);z=add(o,10,[a]);report(a,'in_progress');report(a,'done',10);report(z,'in_progress');report(z,'done',20)
before=admin.call('order&id='+str(o))['reports'];copies(o,20)
assert task(a)['status']=='blocked' and task(z)['status']=='blocked' and int(task(z)['good_quantity'])==20
assert admin.call('order&id='+str(o))['reports']==before
# Anulowanie całego zamówienia blokuje stare formularze, planowanie i dalsze raporty.
o=new_order();a=add(o);z=add(o,10,[a]);done=add(o);report(done,'in_progress');report(done,'done',10);report(a,'in_progress',2)
cancelled=next(s['id'] for s in d['order_statuses'] if s['code']=='cancelled')
order_header=admin.call('order&id='+str(o))['order'];data={'id':o,'version':order_header['version'],'status_id':cancelled,'reason':'TEST rezygnacja klienta'}
admin.call('order_status',data,status=409);v=admin.call('preview_order_status',data);report(a,'in_progress',1)
admin.call('order_status',{**data,'confirmation_token':v['confirmation_token']},status=409)
v=admin.call('preview_order_status',data);before=admin.call('order&id='+str(o))['reports'];admin.call('order_status',{**data,'confirmation_token':v['confirmation_token']})
assert task(a)['status']==task(z)['status']=='cancelled' and task(done)['status']=='done'
assert admin.call('order&id='+str(o))['reports']==before
admin.call('report',report_data(a,'in_progress'),status=422);admin.call('preview_task_update',admin.call('task&id='+str(done)),status=422)
it=admin.call('order&id='+str(o))['items'][0];admin.call('create_task',{'order_item_id':it['id']},status=422)
# Ręczne anulowanie etapu obejmuje rozgałęzionych następców i zapisuje tylko jeden raport.
o=new_order();a=add(o);z=add(o,10,[a]);child=add(o,10,[z]);branch=add(o,10,[a]);done=add(o);report(done,'in_progress');report(done,'done',10);report(a,'in_progress',2)
data=report_data(a,'cancelled');data['good_quantity']=1;v=admin.call('preview_report',data);assert len(v['changes'])>=5;count=len(admin.call('order&id='+str(o))['reports'])
admin.call('report',data,status=409);admin.call('report',{**data,'confirmation_token':v['confirmation_token']})
assert all(task(t)['status']=='cancelled' for t in [a,z,child,branch]);assert task(done)['status']=='done'
assert len(admin.call('order&id='+str(o))['reports'])==count+1
admin.call('report',report_data(z,'in_progress'),status=422)
# Niezależne sesje: raport równoczesny z anulowaniem nie może ominąć blokady.
worker=Client();worker.login('admin',os.environ['ARKUSZ_TEST_PASSWORD'])
def request_status(client,action,data):
 req=urllib.request.Request(os.environ['ARKUSZ_TEST_URL']+'/api.php?action='+action,data=json.dumps(data).encode(),headers={'Content-Type':'application/json','X-CSRF-Token':client.csrf})
 try:
  with client.opener.open(req) as response:return response.status
 except urllib.error.HTTPError as e:return e.code
for _ in range(6):
 o=new_order();a=add(o);header=admin.call('order&id='+str(o))['order'];data={'id':o,'version':header['version'],'status_id':cancelled,'reason':'TEST równoległość'}
 v=admin.call('preview_order_status',data);payload=report_data(a,'in_progress');barrier=threading.Barrier(2)
 def race(client,action,body):barrier.wait();return request_status(client,action,body)
 with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
  f=pool.submit(race,admin,'order_status',{**data,'confirmation_token':v['confirmation_token']});g=pool.submit(race,worker,'report',payload);cancel_code,report_code=f.result(),g.result()
 assert (cancel_code,report_code) in [(200,422),(409,200)],(cancel_code,report_code)
 if cancel_code==409:
  v=admin.call('preview_order_status',data);admin.call('order_status',{**data,'confirmation_token':v['confirmation_token']})
 assert task(a)['status']=='cancelled'
 admin.call('report',report_data(a,'in_progress'),status=422)
# Limiter nie zależy od ciasteczka, wielkości liter loginu ani X-Forwarded-For z niezaufanego źródła.
pwd=secrets.token_urlsafe(18);login='limit-'+secrets.token_hex(4);graphic=next(r['id'] for r in d['roles'] if r['code']=='graphic')
admin.call('create_user',{'login':login,'display_name':'TEST limit','password':pwd,'role_ids':[graphic],'work_center_ids':[]})
url=os.environ['ARKUSZ_TEST_URL']+'/api.php?action=login'
def bad_login(name,headers=None,password='wrong-password'):
 req=urllib.request.Request(url,data=json.dumps({'login':name,'password':password}).encode(),headers={'Content-Type':'application/json',**(headers or {})})
 try:
  with urllib.request.urlopen(req) as r:return r.status
 except urllib.error.HTTPError as e:return e.code
for n in range(10):assert bad_login(login.upper() if n%2 else login)==401
assert bad_login(login,{'X-Forwarded-For':'203.0.113.99'})==429
assert bad_login(login,password=pwd)==429
sql("DELETE FROM login_attempt_limits WHERE bucket_kind IN ('pair','account')")
with concurrent.futures.ThreadPoolExecutor(max_workers=12) as pool:codes=list(pool.map(lambda _:bad_login(login),range(20)))
assert codes.count(401)==10 and codes.count(429)==10,codes
sql('DELETE FROM login_attempt_limits');assert bad_login(login,password=pwd)==200
# Limit źródła także nie jest zerowany przez nowe konta/sesje.
sql("UPDATE login_attempt_limits SET attempts=499 WHERE bucket_kind='source'")
assert bad_login('unknown-'+secrets.token_hex(6))==401
assert bad_login('unknown-'+secrets.token_hex(6))==429
sql('DELETE FROM login_attempt_limits')
# Pliki root i hasło pierwszego administratora nie są dostępne procesowi WWW.
check='exit(is_readable("/run/arkusz-secrets/db-password")&&!file_exists("/run/arkusz-secrets/db-root-password")&&!file_exists("/run/arkusz-secrets/admin-password")?0:1);'
assert compose('exec','-T','--user','33','web','php','-r',check)==b''
assert compose('run','--rm','--no-deps','-T','--user','65534','--entrypoint','php','setup','-r','exit(is_readable("/run/arkusz-secrets/db-password")||is_readable("/run/arkusz-secrets/db-root-password")?1:0);')==b''
assert compose('exec','-T','--user','999','db','sh','-c','test -r /run/arkusz-secrets/db-root-password && test -r /run/arkusz-secrets/db-password')==b''
print('OK: stałe podstawy i fixed, następcy z nadprodukcją, anulowanie zamówienia i grafu, stare formularze, trwały i atomowy limit logowania, niezaufane proxy i izolacja sekretów.')
