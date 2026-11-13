#!/usr/bin/env python3
"""Test już uruchomionego, izolowanego stosu Compose; nie wypisuje haseł."""
import argparse,http.cookiejar,json,os,pathlib,shlex,subprocess,time,urllib.error,urllib.request
parser=argparse.ArgumentParser();parser.add_argument('--engine',default='docker compose');parser.add_argument('--project',default='arkusz-compose-test');parser.add_argument('--url',default='http://127.0.0.1:18080');args=parser.parse_args()
if args.project=='arkusz':raise SystemExit('Test wymaga osobnego projektu, nie instalacji produkcyjnej arkusz.')
command=shlex.split(args.engine)+['-p',args.project]
def compose(*items,input=None):
 return subprocess.check_output(command+list(items),input=input,stderr=subprocess.PIPE)
def wait():
 deadline=time.monotonic()+60
 while time.monotonic()<deadline:
  try:
   with urllib.request.urlopen(args.url+'/health.php',timeout=2) as r:
    if r.status==200:return
  except (OSError,urllib.error.URLError):pass
  time.sleep(.3)
 raise RuntimeError('Aplikacja nie odzyskała gotowości.')
class Client:
 def __init__(self):self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf=''
 def call(self,action,data=None):
  request=urllib.request.Request(args.url+'/api.php?action='+action,data=json.dumps(data).encode() if data is not None else None,headers={'Content-Type':'application/json','X-CSRF-Token':self.csrf})
  with self.opener.open(request,timeout=10) as r:return json.load(r)
 def login(self,password):
  self.call('login',{'login':'admin','password':password});b=self.call('bootstrap');self.csrf=b['csrf'];return b
wait()
config=json.loads(compose('config','--format','json'))
assert not config.get('volumes')
for service in config['services'].values():
 for mount in service.get('volumes',[]):assert mount['type']=='bind' and pathlib.Path(mount['source']).is_absolute()
root=pathlib.Path(config['services']['setup']['environment']['APP_DATA_HOST_PATH'])
if root.resolve()==(pathlib.Path.cwd()/'app_data').resolve():raise SystemExit('Test wymaga osobnego APP_DATA_DIR, nie app_data instalacji użytkowej.')
assert (root/'app/public/index.html').is_file() and (root/'database').is_dir()
assert (root/'sessions').is_dir()
host_password=(root/'config/admin-password').read_text().strip()
password=compose('exec','-T','web','cat','/run/arkusz-secrets/admin-password').decode().strip();assert len(password)>=24 and password==host_password
fingerprints=compose('exec','-T','web','php','-r',"foreach(['admin-password','db-password','db-root-password'] as $f)echo hash_file('sha256','/run/arkusz-secrets/'.$f).PHP_EOL;")
client=Client();boot=client.login(password);assert len(boot['dictionaries']['paper_variants'])>=10
versions=client.call('version');assert versions['application']['version']=='1.0.0' and versions['application']['api_version']=='1.0'
assert versions['database']['schema_version']==1 and versions['database']['schema_release']=='1.0.0'
assert versions['runtime']['php'].startswith('8.4.') and versions['database']['server_version'].startswith('13.')
demo_count=len([o for o in client.call('orders') if o['order_number']=='DEMO/001'])
assert demo_count==0
customer=client.call('customer',{'name':'TEST Compose'})['id']
order=client.call('create_order',{'customer_id':customer,'title':'TEST Compose — trwałość','customer_notes':'Utworzone przez API po instalacji Compose','items':[{'name':'Wizytówki','product_type_id':boot['dictionaries']['product_types'][0]['id'],'quantity':100,'finished_width_mm':90,'finished_height_mm':50,'components':[{'name':'Karta','page_count':2,'paper_variant_id':boot['dictionaries']['paper_variants'][0]['id'],'print_technique':'digital','digital_front_mode':'full_color','digital_back_mode':'none'}]}]})['id']
client.call('save_theme',{'theme':'beige_light'})
for path in ['/.env','/db.php','/config.php','/baza.sql','/bootstrap-secrets.php']:
 try:urllib.request.urlopen(args.url+path);raise AssertionError('Publiczny plik: '+path)
 except urllib.error.HTTPError as e:assert e.code==404
# Zarządzane pliki mają wrócić z obrazu, a wycofane pliki mają zniknąć.
original_index=(root/'app/public/index.html').read_bytes()
(root/'app/public/index.html').write_text('TEST: stara kopia')
(root/'app/removed-by-test.txt').write_text('Nieaktualny zarządzany plik')
manifest=root/'app/.arkusz-files.json';managed=json.loads(manifest.read_text());managed.append('removed-by-test.txt');manifest.write_text(json.dumps(managed))
# Odtworzenie wszystkich kontenerów bez kasowania katalogów danych; hasła i dane mają pozostać.
compose('down');compose('up','-d','--wait');wait()
assert (root/'app/public/index.html').read_bytes()==original_index
assert not (root/'app/removed-by-test.txt').exists()
assert client.call('bootstrap')['user']['theme']=='beige_light'  # sesja również przetrwała

assert fingerprints==compose('exec','-T','web','php','-r',"foreach(['admin-password','db-password','db-root-password'] as $f)echo hash_file('sha256','/run/arkusz-secrets/'.$f).PHP_EOL;")
client=Client();assert client.login(password)['user']['theme']=='beige_light';assert client.call('order&id='+str(order))['order']['customer_notes']=='Utworzone przez API po instalacji Compose'
assert len([o for o in client.call('orders') if o['order_number']=='DEMO/001'])==demo_count
# Obie kopie są trzymane w prywatnym katalogu /tmp; nie trafiają do repozytorium.
backup=pathlib.Path('/tmp/arkusz-compose-backup');backup.mkdir(exist_ok=True,mode=0o700)
sql=compose('exec','-T','db','sh','-c','exec mariadb-dump -udrukarnia -p"$(cat "$MARIADB_PASSWORD_FILE")" --single-transaction drukarnia')
credentials=compose('run','--rm','--no-deps','-T','--entrypoint','tar','setup','-C','/run/arkusz-secrets','-cf','-','.')
for name,data in [('baza.sql',sql),('config.tar',credentials)]:
 fd=os.open(backup/name,os.O_WRONLY|os.O_CREAT|os.O_TRUNC,0o600)
 with os.fdopen(fd,'wb') as f:f.write(data)
assert b'CREATE TABLE `orders`' in sql and len(credentials)>1000
assert (root/'app/public/index.html').is_file() and (root/'config/admin-password').read_text().strip()==password
print('OK: bezwzględne bind mounty bez nazwanych wolumenów, pliki aplikacji i hasło na dysku, instalacja od zera, losowe hasła, logowanie, zapis zamówienia, prywatne pliki, ponowne tworzenie kontenerów bez utraty danych i haseł, bez duplikowania przykładu, kopie bazy i konfiguracji.')
