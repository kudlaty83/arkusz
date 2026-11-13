#!/usr/bin/env python3
"""Integracja potwierdzanej edycji, przeliczania planu i zachowania historii."""
import copy,secrets
from smoke import admin,b,d,order,Client,task,report,base_task,first,second

def payload(id):
 detail=admin.call('order&id='+str(id));o=detail['order'];data={k:o[k] for k in ['id','version','customer_id','title','priority','contact_name','contact_email','contact_phone','customer_notes','internal_notes']}
 data['due_at']=o['due_at'].replace(' ','T')[:16] if o['due_at'] else ''
 data['items']=detail['items']
 for item in data['items']:
  features=item.pop('features')
  for f in features:f['values']={v['code']:v['value_text'] if v['value_text'] is not None else v['value_number'] if v['value_number'] is not None else v['value_boolean'] for v in f['values']}
  item['features']=[f for f in features if f['scope']=='item']
  for c in item['components']:
   for side in ['front','back']:c[side+'_inks']=[ink['ink_name'] for ink in c['inks'] if ink['side']==side]
   c['features']=[f for f in features if f['scope']=='component' and f['component_id']==c['id']]
 return data

def preview(p):return admin.call('preview_order_update',p)
def save(p):
 result=preview(p);return admin.call('update_order',{**p,'confirmation_token':result['confirmation_token']})
def detail():return admin.call('order&id='+str(order))

# Sam podgląd niczego nie utrwala, a zapis bez zatwierdzenia jest blokowany.
original=detail();tasks_before=admin.call('tasks');p=payload(order);p['items'][0]['quantity']=20;p['due_at']='2027-02-15T11:30'
admin.call('update_order',p,status=409)
v=preview(p);assert v['task_count']==3 and any('10 → 20' in line for line in v['changes'])
assert detail()==original and admin.call('tasks')==tasks_before
# Potwierdzenie innej zawartości formularza jest odrzucane.
wrong=copy.deepcopy(p);wrong['title']='Zmieniony po podglądzie';admin.call('update_order',{**wrong,'confirmation_token':v['confirmation_token']},status=409)
admin.call('update_order',{**p,'confirmation_token':v['confirmation_token']})
after=detail();assert after['order']['version']==original['order']['version']+1 and after['reports']==original['reports']
assert after['order']['due_at']=='2027-02-15 11:30:00'
for old in tasks_before:
 if old['order_id']==order:
  new=task(old['id']);assert new['planned_quantity']==20 and int(new['good_quantity'])==10 and new['status']=='blocked' and new['plan_revision']==1 and new['due_at']=='2027-02-15 11:30:00'
assert any(h['event_type']=='plan' for h in after['task_history']) and after['changes']
assert next(o for o in admin.call('orders') if o['id']==order)['status_code']=='production'
admin.call('update_order',{**p,'confirmation_token':v['confirmation_token']},status=409)
# Raport po podglądzie unieważnia zgodę, bez zapisu zamówienia.
p=payload(order);p['due_at']='2027-02-16T11:30';v=preview(p);before=detail()['order']
report(first,'in_progress',2)
admin.call('update_order',{**p,'confirmation_token':v['confirmation_token']},status=409);assert detail()['order']==before
save(p);assert int(task(first)['good_quantity'])==12 and task(first)['due_at']=='2027-02-16 11:30:00'
# Nowe zadanie po podglądzie także unieważnia zgodę.
p=payload(order);p['internal_notes']='Organizacja';v=preview(p)
extra=admin.call('create_task',{**base_task,'planned_quantity':30,'sequence_number':40})['id']
admin.call('update_order',{**p,'confirmation_token':v['confirmation_token']},status=409);save(p)
# Przeliczenie różnych jednostek zachowuje dotychczasowy współczynnik; zaokrągla w górę.
p=payload(order);p['items'][0]['quantity']=21;save(p);assert task(extra)['planned_quantity']==32
# Spadek nakładu nie kasuje wykonania ani nie kończy automatycznie zadania.
p=payload(order);p['items'][0]['quantity']=7;save(p);assert task(first)['planned_quantity']==7 and int(task(first)['good_quantity'])==12 and task(first)['status']=='in_progress'
# Uwagi nie zerują wykonania; zmiana technologii tworzy nową rewizję i wstrzymuje etapy.
p=payload(order);p['customer_notes']='Ustalenia\nąćęłńóśźż';p['items'][0]['components'][0]['notes']='Nowe uwagi';save(p);assert int(task(first)['good_quantity'])==12 and task(first)['plan_revision']==1
old_reports=detail()['reports'];p=payload(order);p['items'][0]['components'][0]['front_inks']=['K','Pantone 186'];save(p)
assert task(first)['plan_revision']==2 and int(task(first)['good_quantity'])==0 and task(first)['status']=='blocked';assert detail()['reports']==old_reports
assert task(second)['plan_revision']==2
report(first,'in_progress');report(first,'done',0,expected=422);report(first,'done',7)
# Nieaktywny papier można zachować, a migawki nie zmieniają się od edycji słownika.
p=payload(order);component=p['items'][0]['components'][0];cat=admin.call('dictionary_catalog');paper=next(x for x in cat['categories']['paper_variants']['entries'] if x['id']==component['paper_variant_id'])
admin.call('dictionary_save',{'category':'paper_variants',**paper,'name':'TEST zmieniony papier','grammage_gsm':300,'is_active':0})
try:
 save(p);updated=detail()['items'][0]['components'][0];assert updated['paper_description']==component['paper_description'] and updated['grammage_gsm']==component['grammage_gsm']
finally:admin.call('dictionary_save',{'category':'paper_variants',**paper})
# Zmiana rodzaju powiązanej cechy zachowuje stary rekord i wiąże zadanie z nowym.
p=payload(order);c=p['items'][0]['components'][0];old_feature=c['features'][0]['id'];other=next(f for f in d['feature_types'] if f['id']!=c['features'][0]['feature_type_id'] and f['scope'] in ['both','component'])
c['features'][0].update(feature_type_id=other['id'],values={'kind':'Nowa specyfikacja','side':'Awers'});save(p)
new_feature=next(f for f in detail()['items'][0]['features'] if f['scope']=='component');assert new_feature['id']!=old_feature
# Usunięcie powiązanej cechy anuluje niezakończone zadanie i jego następników.
dependent=admin.call('create_task',{**base_task,'planned_quantity':7,'sequence_number':50,'depends_on':[second]})['id']
p=payload(order);p['items'][0]['components'][0]['features']=[];v=preview(p);assert any('Usunięcie cechy' in x for x in v['changes']);admin.call('update_order',{**p,'confirmation_token':v['confirmation_token']})
assert task(second)['status']=='cancelled' and task(dependent)['status']=='cancelled'
# Dodanie i usunięcie produktów/części pozostawia zadania i raporty w historii.
p=payload(order);new=copy.deepcopy(p['items'][0]);new.pop('id');new['name']='Dodatkowy produkt';new['features']=[]
for c in new['components']:c.pop('id');c['features']=[]
p['items'].append(new);save(p);new_id=detail()['items'][1]['id']
new_task=admin.call('create_task',{**base_task,'order_item_id':new_id,'component_id':detail()['items'][1]['components'][0]['id'],'planned_quantity':7})['id']
p=payload(order);p['items'].pop();save(p);assert len(detail()['items'])==1 and task(new_task)['status']=='cancelled'
p=payload(order);removed_component=p['items'][0]['components'][0]['id'];p['items'][0]['components'].pop(0);save(p);assert all(c['id']!=removed_component for c in detail()['items'][0]['components']);assert task(first)['status']=='done'
# Edycja kartoteki klienta po podglądzie również wymaga ponownego zatwierdzenia.
p=payload(order);v=preview(p);customer=admin.call('customer&id='+str(p['customer_id']))
admin.call('update_customer',{**customer,'phone':'TEST konflikt kartoteki'})
admin.call('update_order',{**p,'confirmation_token':v['confirmation_token']},status=409)
save(p)
# Usunięcie terminu obejmuje niezakończone zadania i nie usuwa raportów.
p=payload(order);p['due_at']='';save(p)
assert detail()['order']['due_at'] is None
assert all(t['due_at'] is None for t in admin.call('tasks') if t['order_id']==order and t['status'] not in ['done','cancelled'])
# Walidacja i rollback w podglądzie, CSRF i role.
before=detail();p=payload(order);p['items'][0]['components'][0]['print_technique']='nieprawidłowa';admin.call('preview_order_update',p,status=422);assert detail()==before
p=payload(order);admin.call('preview_order_update',p,status=403,csrf=False);admin.call('update_order',p,status=403,csrf=False)
worker=Client();pwd=secrets.token_urlsafe(18);login='edit-'+secrets.token_hex(4);admin.call('create_user',{'login':login,'display_name':'TEST brak edycji','password':pwd,'role_ids':[next(r['id'] for r in d['roles'] if r['code']=='graphic')],'work_center_ids':[]});worker.login(login,pwd);worker.call('preview_order_update',p,status=403)
print('OK: podgląd bez zapisu, potwierdzenie konkretnego planu, konflikty raportów i nowych zadań, proporcje, terminy, rewizje wykonania, archiwizacja, anulowanie zależności, historia, rollback i uprawnienia.')
