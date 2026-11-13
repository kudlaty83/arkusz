#!/usr/bin/env python3
"""Edycja klientów i kont, stronicowanie, uprawnienia oraz unieważnianie sesji."""
import copy,json,secrets,urllib.parse
from smoke import admin,b,d,order,item,Client,base_task

def detail():return admin.call('order&id='+str(order))
def edit_customer(id,**changes):return {**admin.call('customer&id='+str(id)),**changes}
def edit_user(id,**changes):return {**admin.call('user&id='+str(id)),**changes}

tag=secrets.token_hex(5);prefix='TEST klienci '+tag+' '
boot_before=admin.call('bootstrap');assert 'customers' not in boot_before
ids=[]
for n in range(123):ids.append(admin.call('customer',{'name':prefix+f'{n//3:03d}','tax_id':tag+str(n),'email':f'client-{tag}-{n}@example.test','phone':f'555{n:06d}'})['id'])
assert admin.call('bootstrap')==boot_before
seen=[]
for page in range(1,14):
 p=admin.call('customers&'+urllib.parse.urlencode({'q':prefix,'page':page,'page_size':10,'active':'all'}));assert p['total']==123 and len(p['entries'])<=10 and p['pages']==13;seen.extend(c['id'] for c in p['entries'])
assert len(seen)==len(set(seen))==123 and set(seen)==set(ids)
assert admin.call('customers&'+urllib.parse.urlencode({'q':prefix,'page':999999,'page_size':10}))['page']==13
admin.call('customers&page_size=11',status=422);admin.call('customers&page=0',status=422)
literal=admin.call('customer',{'name':prefix+'%literal'})['id']
assert [c['id'] for c in admin.call('customers&'+urllib.parse.urlencode({'q':prefix+'%'}))['entries']]==[literal]
assert admin.call('customers&q=client-'+tag+'-122')['total']==1
assert admin.call('customers&q=555000122')['total']==1
# Zapis nowej kartoteki nie zmienia migawek w istniejących zamówieniach.
o=detail()['order'];customer_id=o['customer_id'];p=edit_customer(customer_id,name=prefix+'edycja',tax_id='1234567890',email='changed@example.test',phone='123456789',address_text='Nowy adres',notes='Uwagi — ąćęłńóśźż')
admin.call('update_customer',p);after=admin.call('customer&id='+str(customer_id));assert after['version']==p['version']+1 and after['notes']==p['notes'];assert detail()['order']==o
admin.call('update_customer',p,status=409)
admin.call('update_customer',{**after,'email':'błędny adres'},status=422);assert admin.call('customer&id='+str(customer_id))==after
admin.call('update_customer',edit_customer(customer_id,is_active=0));assert admin.call('customers&'+urllib.parse.urlencode({'q':prefix+'edycja','active':1}))['total']==0
assert admin.call('customers&'+urllib.parse.urlencode({'q':prefix+'edycja','active':0}))['total']==1
admin.call('create_order',{'customer_id':customer_id,'title':'TEST nieaktywny klient','items':[copy.deepcopy(item)]},status=422)
admin.call('update_customer',edit_customer(customer_id,is_active=1))
# Konto, login, role i stanowiska; zapis nie usuwa przydziałów ani raportów.
password=secrets.token_urlsafe(20);login='people-'+tag;graphic=next(r['id'] for r in d['roles'] if r['code']=='graphic');manager=next(r['id'] for r in d['roles'] if r['code']=='manager');admin_role=next(r['id'] for r in d['roles'] if r['code']=='admin');center=d['work_centers'][0]['id']
uid=admin.call('create_user',{'login':login,'display_name':'TEST Pracownik '+tag,'password':password,'role_ids':[graphic],'work_center_ids':[center]})['id']
u=admin.call('user&id='+str(uid));assert 'password_hash' not in u and 'auth_version' not in u
worker=Client();worker.login(login,password);other=Client();other.login(login,password)
worker.call('user&id='+str(uid),status=403);worker.call('update_customer',after,status=403);worker.call('update_user',u,status=403)
task_id=admin.call('create_task',{**base_task,'assignee_ids':[uid]})['id'];t=next(t for t in admin.call('tasks') if t['id']==task_id)
worker.call('report',{'task_id':task_id,'version':t['version'],'status_after':'in_progress','good_quantity':1,'waste_quantity':0,'work_minutes':3,'notes':'TEST przed edycją konta'})
reports=[(r['id'],r['reported_by'],r['good_quantity'],r['notes']) for r in detail()['reports']]
p=edit_user(uid,login=login+'-new',display_name='TEST Nowe nazwisko '+tag,role_ids=[manager],work_center_ids=[])
admin.call('update_user',p);admin.call('update_user',p,status=409);worker.call('bootstrap',status=401);other.call('bootstrap',status=401)
worker.login(login+'-new',password);assert 'tasks.manage' in worker.call('bootstrap')['permissions']
t=next(t for t in admin.call('tasks') if t['id']==task_id);assert str(uid) in t['assignee_ids'].split(',')
assert [(r['id'],r['reported_by'],r['good_quantity'],r['notes']) for r in detail()['reports']]==reports
saved=admin.call('user&id='+str(uid));admin.call('update_user',{**saved,'login':'admin'},status=409);assert admin.call('user&id='+str(uid))==saved
# Wyłączenie i ponowne włączenie nie przywraca starych sesji.
admin.call('update_user',edit_user(uid,is_active=0));worker.call('bootstrap',status=401);worker.call('login',{'login':login+'-new','password':password},status=401)
admin.call('update_user',edit_user(uid,is_active=1));worker.call('bootstrap',status=401);worker.login(login+'-new',password)
# Zmiana własnego hasła utrzymuje tylko bieżącą sesję i odnawia CSRF.
other=Client();other.login(login+'-new',password);new_password=secrets.token_urlsafe(22)
change={'current_password':password,'password':new_password,'password_confirmation':new_password}
worker.call('change_password',{**change,'current_password':'błędne hasło'},status=422)
worker.call('change_password',{**change,'password_confirmation':'inne hasło'},status=422)
worker.call('change_password',{**change,'password':'123','password_confirmation':'123'},status=422)
worker.call('change_password',{**change,'password':'x'*73,'password_confirmation':'x'*73},status=422)
worker.call('change_password',change,status=403,csrf=False)
result=worker.call('change_password',change);old_csrf=worker.csrf;worker.csrf=result['csrf'];assert worker.csrf!=old_csrf
assert worker.call('bootstrap')['user']['id']==uid;other.call('bootstrap',status=401)
other.call('login',{'login':login+'-new','password':password},status=401);other.login(login+'-new',new_password)
# Administrator ustawia hasło innego konta; istniejące sesje tracą dostęp.
reset_password=secrets.token_urlsafe(22);target=edit_user(uid)
reset={'id':uid,'version':target['version'],'password':reset_password,'password_confirmation':reset_password}
worker.call('reset_password',reset,status=403);admin.call('reset_password',reset,status=403,csrf=False)
admin.call('reset_password',{**reset,'version':target['version']-1},status=409)
admin.call('reset_password',reset);worker.call('bootstrap',status=401);other.call('bootstrap',status=401)
worker.login(login+'-new',reset_password)
admin.call('reset_password',{**reset,'id':b['user']['id']},status=422)
# Nie można wyłączyć siebie ani odebrać ostatniej roli administratora.
my=edit_user(b['user']['id']);admin.call('update_user',{**my,'is_active':0},status=422);admin.call('update_user',{**my,'role_ids':[graphic]},status=422)
# Konta nieaktywne są dostępne administratorowi, a pracownik widzi tylko aktywne.
admin.call('update_user',edit_user(uid,is_active=0));viewer=Client();viewer_password=secrets.token_urlsafe(18);viewer_login='viewer-'+tag
viewer_id=admin.call('create_user',{'login':viewer_login,'display_name':'TEST podgląd '+tag,'password':viewer_password,'role_ids':[graphic],'work_center_ids':[]})['id'];viewer.login(viewer_login,viewer_password)
assert any(x['id']==uid for x in admin.call('users&active=0')['entries']);assert all(x['is_active'] for x in viewer.call('users&active=all')['entries'])
admin.call('update_customer',edit_customer(customer_id),status=403,csrf=False);admin.call('update_user',my,status=403,csrf=False)
assert all('password_hash' not in json.dumps(x) for x in [admin.call('users'),admin.call('bootstrap')])
print('OK: 123 klientów, stabilne strony, wyszukiwanie prefiksów i dosłownych znaków %, bounded bootstrap, edycja i konflikty, migawki zamówień, konta i role, zachowanie przydziałów/raportów, zmiana/reset hasła, sesje, CSRF i ochrona administratora.')
