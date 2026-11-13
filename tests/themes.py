#!/usr/bin/env python3
"""Preferencje wyglądu są przypisane do konta, nie przeglądarki."""
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
admin=Client();guest=Client();guest.call('save_theme',{'theme':'black'},status=401)
env=dict(line.split('=',1) for line in (pathlib.Path('.env').read_text() if pathlib.Path('.env').exists() else 'ADMIN_PASSWORD=').splitlines());b=admin.login('admin',os.environ.get('ARKUSZ_TEST_PASSWORD',env['ADMIN_PASSWORD']));original=b['user']['theme']
login='theme-'+secrets.token_hex(4);password=secrets.token_urlsafe(18)
admin.call('create_user',{'login':login,'display_name':'TEST motyw','password':password,'role_ids':[next(r['id'] for r in b['dictionaries']['roles'] if r['code']=='graphic')],'work_center_ids':[]})
worker=Client();assert worker.login(login,password)['user']['theme']=='green'
for theme in ['beige','beige_light','gray','black','green']:
 assert worker.call('save_theme',{'theme':theme,'user_id':b['user']['id']})=={'theme':theme}
 other_session=Client();assert other_session.login(login,password)['user']['theme']==theme
 assert admin.call('bootstrap')['user']['theme']==original
worker.call('save_theme',{'theme':'black'},csrf=False,status=403)
for value in ['pink','',None,1,False,[],{}]:worker.call('save_theme',{'theme':value},status=422)
assert worker.call('bootstrap')['user']['theme']=='green'
worker.call('logout',{});worker.call('save_theme',{'theme':'beige'},status=401)
print('OK: pięć motywów, domyślny zielony, osobne konta, zapis między sesjami, walidacja i CSRF; preferencja administratora bez zmian.')
