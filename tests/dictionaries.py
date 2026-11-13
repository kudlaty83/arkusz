#!/usr/bin/env python3
"""Testy edytora słowników przez HTTP; pozostawiają oznaczone dane testowe."""
import os
if os.environ.get("ARKUSZ_ALLOW_TEST_DATA")!="1" or not os.environ.get("ARKUSZ_TEST_URL"):raise SystemExit("Test zapisuje dane. Użyj osobnej instalacji i ustaw ARKUSZ_ALLOW_TEST_DATA=1 oraz ARKUSZ_TEST_URL.")
import http.cookiejar,json,pathlib,secrets,urllib.request,urllib.error
class Client:
 def __init__(self):self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf=''
 def call(self,action,data=None,status=200,csrf=True):
  req=urllib.request.Request(os.environ.get('ARKUSZ_TEST_URL','http://127.0.0.1:8080')+'/api.php?action='+action,data=json.dumps(data).encode() if data is not None else None,headers={'Content-Type':'application/json',**({'X-CSRF-Token':self.csrf} if csrf else {})})
  try:
   with self.opener.open(req) as r:code=r.status;body=json.load(r)
  except urllib.error.HTTPError as e:code=e.code;body=json.load(e)
  assert code==status,(action,code,status,body)
  return body
 def login(self,login,password):
  self.call('login',{'login':login,'password':password});b=self.call('bootstrap');self.csrf=b['csrf'];return b
admin=Client();admin.call('dictionary_catalog',status=401)
env=dict(line.split('=',1) for line in (pathlib.Path('.env').read_text() if pathlib.Path('.env').exists() else 'ADMIN_PASSWORD=').splitlines())
b=admin.login('admin',os.environ.get('ARKUSZ_TEST_PASSWORD',env['ADMIN_PASSWORD']));tag=secrets.token_hex(4);catalog=admin.call('dictionary_catalog');assert len(catalog['categories'])==10
view=next(p['id'] for p in catalog['permissions'] if p['code']=='orders.view')
created={}
for category,definition in catalog['categories'].items():
 data={'category':category}
 for f in definition['fields']:
  k=f['key'];t=f['type']
  if t=='code':data[k]='test_'+tag+'_'+str(len(created))
  elif t=='reference':data[k]=created.get(f['source'],catalog['categories'][f['source']]['entries'][0]['id'])
  elif t=='permissions':data[k]=[view]
  elif t=='boolean':data[k]=f.get('default',0)
  elif t=='integer':data[k]=max(f['min'],f.get('default',130))
  elif t=='select':data[k]=f.get('default',next(iter(f['options'])))
  else:data[k]='TEST '+tag if k=='name' else ''
 id=admin.call('dictionary_save',data)['id'];created[category]=id
 data['id']=id;data['name']='TEST mod '+tag
 admin.call('dictionary_save',data)
 if 'code'in data:admin.call('dictionary_save',{**data,'code':'changed'},status=422)
 if 'is_active'in data:
  admin.call('dictionary_save',{**data,'is_active':0});assert not next(e for e in admin.call('dictionary_catalog')['categories'][category]['entries'] if e['id']==id)['is_active']
  admin.call('dictionary_save',data)
 if category=='paper_variants':admin.call('dictionary_save',{**data,'grammage_gsm':0},status=422)
 if category=='roles':
  admin.call('dictionary_save',{**data,'permission_ids':[]},status=422)
  assert [r for r in admin.call('dictionary_catalog')['role_permissions'] if r['role_id']==id]==[{'role_id':id,'permission_id':view}]
admin.call('dictionary_save',{'category':'users;DROP TABLE orders','name':'Nie'},status=404)
admin.call('dictionary_save',{'category':'paper_types','name':'CSRF'},status=403,csrf=False)
role=next(r for r in catalog['categories']['roles']['entries'] if r['code']=='admin')
admin.call('dictionary_save',{'category':'roles',**role,'permission_ids':[view]},status=422)
status=next(s for s in catalog['categories']['order_statuses']['entries'] if s['code']=='new')
admin.call('dictionary_save',{'category':'order_statuses',**status,'is_active':0},status=422)
# Unikatowe nazwy rodzajów papieru oraz role muszą zachować spójność po błędzie.
admin.call('dictionary_save',{'category':'paper_types','name':catalog['categories']['paper_types']['entries'][0]['name'],'is_active':1},status=409)
# Własna rola ogranicza dostęp do słowników na poziomie serwera.
password=secrets.token_urlsafe(18);login='dict-'+tag
admin.call('create_user',{'login':login,'display_name':'TEST słowniki '+tag,'password':password,'role_ids':[created['roles']],'work_center_ids':[]})
worker=Client();worker.login(login,password);worker.call('dictionary_catalog',status=403);worker.call('dictionary_save',{'category':'paper_types','name':'Brak dostępu'},status=403)
# Nieaktywne katalogi nie mogą być używane do nowych zleceń, nawet z bezpośredniego API.
pt=next(e for e in admin.call('dictionary_catalog')['categories']['paper_types']['entries'] if e['id']==created['paper_types'])
admin.call('dictionary_save',{'category':'paper_types',**pt,'is_active':0})
item={'name':'TEST nieaktywny papier','product_type_id':b['dictionaries']['product_types'][0]['id'],'quantity':1,'finished_width_mm':90,'finished_height_mm':50,'components':[{'name':'Całość','paper_variant_id':created['paper_variants'],'page_count':2,'print_technique':'digital','digital_front_mode':'bw','digital_back_mode':'none'}]}
admin.call('create_order',{'customer_id':admin.call('customer',{'name':'TEST papier klient '+tag})['id'],'title':'TEST nieaktywny','items':[item]},status=422)
# Typ parametru z zapisanymi wartościami i zakres używanej cechy są chronione.
used=b['dictionaries']['feature_parameter_definitions'][0]
admin.call('dictionary_save',{'category':'feature_parameter_definitions',**used,'value_type':'number'},status=422)
# Własne statusy końcowe i kolejność są respektowane przez listę zamówień.
custom=next(e for e in admin.call('dictionary_catalog')['categories']['order_statuses']['entries'] if e['id']==created['order_statuses'])
admin.call('dictionary_save',{'category':'order_statuses',**custom,'sort_order':-5,'is_terminal':1})
boot=admin.call('bootstrap');statuses=boot['dictionaries']['order_statuses'];assert [(s['sort_order'],s['id']) for s in statuses]==sorted((s['sort_order'],s['id']) for s in statuses);assert next(s for s in statuses if s['id']==custom['id'])['sort_order']==-5
customer=admin.call('customer',{'name':'TEST status '+tag})['id']
item['components'][0]['paper_variant_id']=b['dictionaries']['paper_variants'][0]['id']
order=admin.call('create_order',{'customer_id':customer,'title':'TEST status '+tag,'items':[item]})['id']
admin.call('order_status',{'id':order,'version':1,'status_id':custom['id']})
assert next(o for o in admin.call('orders') if o['id']==order)['status_terminal']==1
print('OK: 10 kategorii, dodawanie, edycja, aktywność, role, walidacja, CSRF, ochrona systemowych kodów, parametrów i administratora, blokada nieaktywnych papierów.')
