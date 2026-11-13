#!/usr/bin/env python3
"""Test integracyjny uruchomionej aplikacji, bez bibliotek zewnętrznych."""
import os
if os.environ.get("ARKUSZ_ALLOW_TEST_DATA")!="1" or not os.environ.get("ARKUSZ_TEST_URL"):raise SystemExit("Test zapisuje dane. Użyj osobnej instalacji i ustaw ARKUSZ_ALLOW_TEST_DATA=1 oraz ARKUSZ_TEST_URL.")
import http.cookiejar,json,pathlib,secrets,urllib.request,urllib.error
BASE=os.environ.get('ARKUSZ_TEST_URL','http://127.0.0.1:8080')
env=dict(line.strip().split('=',1) for line in (pathlib.Path('.env').read_text() if pathlib.Path('.env').exists() else 'ADMIN_PASSWORD=').splitlines() if '=' in line)
class Client:
 def __init__(self):
  self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf=''
 def call(self,action,data=None,status=200,csrf=True):
  request=urllib.request.Request(BASE+'/api.php?action='+action,data=json.dumps(data).encode() if data is not None else None,headers={'Content-Type':'application/json',**({'X-CSRF-Token':self.csrf} if csrf else {})})
  try:
   with self.opener.open(request) as r:code=r.status;result=json.load(r)
  except urllib.error.HTTPError as e:code=e.code;result=json.load(e)
  assert code==status,(action,code,status,result)
  return result
 def login(self,login,password):
  self.call('login',{'login':login,'password':password});b=self.call('bootstrap');self.csrf=b['csrf'];return b
admin=Client();admin.call('orders',status=401)
b=admin.login('admin',os.environ.get('ARKUSZ_TEST_PASSWORD',env['ADMIN_PASSWORD']));d=b['dictionaries'];uid=b['user']['id'];tag=secrets.token_hex(4)
admin.call('customer',{'name':'Nie powinno powstać'},status=403,csrf=False)
c=admin.call('customer',{'name':'TEST integracyjny '+tag,'email':'test@example.test'})['id']
feature_id=d['feature_types'][0]['id']
component={'name':'Okładka','page_count':4,'paper_variant_id':d['paper_variants'][0]['id'],'print_technique':'offset','offset_front_colors':2,'offset_back_colors':0,'front_inks':['K','Pantone 185'],'back_inks':[],'features':[{'feature_type_id':feature_id,'values':{'kind':'Wypukłe','side':'Awers'},'notes':'Test tłoczenia'}]}
item={'name':'Książka testowa','product_type_id':d['product_types'][2]['id'],'quantity':10,'finished_width_mm':148,'finished_height_mm':210,'components':[component,{'name':'Środek','page_count':32,'paper_variant_id':d['paper_variants'][0]['id'],'print_technique':'digital','digital_front_mode':'bw','digital_back_mode':'bw'}],'features':[{'feature_type_id':feature_id,'values':{'kind':'Test produktu'}}]}
order=admin.call('create_order',{'customer_id':c,'title':'TEST '+tag,'items':[item]})['id']
view=admin.call('order&id='+str(order));it=view['items'][0];assert len(it['components'])==2 and len(it['features'])==2;assert len(it['components'][0]['inks'])==2
# Nieprawidłowe kolory i wymagane parametry nie pozostawiają zamówienia w bazie.
bad=json.loads(json.dumps(item));bad['components'][0]['offset_front_colors']=6
count=len(admin.call('orders'));admin.call('create_order',{'customer_id':c,'title':'BŁĄD','items':[bad]},status=422);assert len(admin.call('orders'))==count
bad=json.loads(json.dumps(item));bad['features'][0]['values']={}
admin.call('create_order',{'customer_id':c,'title':'BŁĄD','items':[bad]},status=422);assert len(admin.call('orders'))==count
center=d['work_centers'][0]['id'];op=d['operation_types'][0]['id']
base_task={'order_item_id':it['id'],'component_id':it['components'][0]['id'],'operation_type_id':op,'work_center_id':center,'planned_quantity':10,'quantity_unit':'szt.','assignee_ids':[uid],'sequence_number':10}
first=admin.call('create_task',base_task)['id'];second=admin.call('create_task',{**base_task,'depends_on':[first],'sequence_number':20,'component_feature_ids':[f['id'] for f in it['features'] if f['scope']=='component']})['id']
def task(id):return next(t for t in admin.call('tasks') if t['id']==id)
def report(id,status,good=0,expected=200,version=None,client=admin):
 t=task(id);return client.call('report',{'task_id':id,'version':version or t['version'],'status_after':status,'good_quantity':good,'waste_quantity':0,'work_minutes':5,'notes':'Test integracyjny'},status=expected)
report(second,'in_progress',expected=422)
report(first,'done',10,expected=422)
original=task(first)['version'];report(first,'in_progress');report(first,'in_progress',version=original,expected=409)
report(first,'done',9,expected=422);report(first,'done',10);report(second,'in_progress');report(second,'done',10)
# Uprawnienia pracownika i przypisania stanowiska / zadania.
password=secrets.token_urlsafe(18);login='test-'+tag
worker=admin.call('create_user',{'login':login,'display_name':'TEST Grafik '+tag,'password':password,'role_ids':[next(r['id'] for r in d['roles'] if r['code']=='graphic')],'work_center_ids':[center]})['id']
w=Client();w.login(login,password)
w.call('customer',{'name':'Niedozwolone'},status=403)
w.call('create_task',base_task,status=403)
third=admin.call('create_task',base_task)['id'];report(third,'in_progress',expected=403,client=w)
t=task(third);admin.call('assign_task',{'id':third,'version':t['version'],'assignee_ids':[worker]});report(third,'in_progress',client=w);report(third,'done',10,client=w)
# Status i konflikt równoczesnych zmian.
ready=next(s['id'] for s in d['order_statuses'] if s['code']=='ready')
o=admin.call('order&id='+str(order))['order'];admin.call('order_status',{'id':order,'version':o['version'],'status_id':ready});admin.call('order_status',{'id':order,'version':o['version'],'status_id':ready},status=409)
assert len(admin.call('order&id='+str(order))['reports'])==6
w.call('logout',{});w.call('orders',status=401)
print('OK: logowanie, CSRF, role, wiele części, offset/cyfra, cechy, walidacja i rollback, zależności, raporty, przydziały, historia, konflikty wersji.')
print('Zamówienie testowe:',order)
