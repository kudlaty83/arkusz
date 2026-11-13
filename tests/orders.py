#!/usr/bin/env python3
"""Testy edycji i zachowania relacji. Import smoke wykonuje bazowy przebieg produkcji."""
import copy,secrets
from smoke import admin,b,d,order,w,env,Client

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

original=admin.call('order&id='+str(order));before_tasks=[t for t in admin.call('tasks') if t['order_id']==order]
p=payload(order);p['title']='TEST edycja '+secrets.token_hex(3);p['customer_notes']='Informacje do zamówienia\nDruga linia — ąćęłńóśźż';p['internal_notes']='Organizacja pracy';p['contact_name']='Anna Test';p['contact_email']='anna@example.test';p['contact_phone']='123456789';p['due_at']='2027-02-15T11:30';p['priority']='urgent'
p['items'][0]['specification_notes']='Informacje do produktu';p['items'][0]['quantity']=20;p['items'][0]['finished_width_mm']=150
p['items'][0]['components'][0]['notes']='Informacje do okładki\nLakier tylko na awersie';p['items'][0]['components'][0]['bleed_mm']=5;p['items'][0]['components'][0]['front_inks']=['K','Pantone 186'];p['items'][0]['components'][0]['features'][0]['values']['kind']='Tłoczenie zmienione'
p['items'][0]['components'][1]['notes']='Osobna informacja do środka'
admin.call('update_order',p)
after=admin.call('order&id='+str(order));assert after['order']['version']==p['version']+1;assert after['order']['customer_notes']==p['customer_notes'];assert after['order']['due_at']=='2027-02-15 11:30:00';assert after['order']['contact_email']=='anna@example.test'
assert after['items'][0]['components'][0]['id']==original['items'][0]['components'][0]['id'];assert after['items'][0]['components'][0]['notes']==p['items'][0]['components'][0]['notes'];assert float(after['items'][0]['components'][0]['bleed_mm'])==5
assert after['items'][0]['features'][1]['id']==original['items'][0]['features'][1]['id'];assert after['reports']==original['reports'];assert [{k:v for k,v in t.items() if k!='order_title'} for t in admin.call('tasks') if t['order_id']==order]==[{k:v for k,v in t.items() if k!='order_title'} for t in before_tasks]
assert len(after['history'])==len(original['history'])+1
admin.call('update_order',p,status=409)
p=payload(order);p['title']='Nie może zostać zapisane';p['items'][0]['components'][0]['offset_front_colors']=6
admin.call('update_order',p,status=422);assert admin.call('order&id='+str(order))==after
p=payload(order);p['items'][0]['components'].pop();admin.call('update_order',p,status=422)
p=payload(order);p['items'][0]['components'][0]['features']=[];admin.call('update_order',p,status=422)
p=payload(order);p['items'][0]['id']=admin.call('order&id=1')['items'][0]['id'];admin.call('update_order',p,status=422)
# Przełączanie offset/cyfra usuwa niepasujące parametry w jednym zapisie.
p=payload(order);c=p['items'][0]['components'][0];c.update(print_technique='digital',digital_front_mode='full_color',digital_back_mode='none');admin.call('update_order',p)
c=admin.call('order&id='+str(order))['items'][0]['components'][0];assert c['offset_front_colors'] is None and c['digital_back_mode']=='none' and c['inks']==[]
p=payload(order);c=p['items'][0]['components'][0];c.update(print_technique='offset',offset_front_colors=1,offset_back_colors=0,front_inks=['Pantone 300'],back_inks=[]);admin.call('update_order',p)
c=admin.call('order&id='+str(order))['items'][0]['components'][0];assert c['digital_front_mode'] is None and c['inks'][0]['ink_name']=='Pantone 300'
# Dodanie nowej części i produktu nie zmienia istniejących identyfikatorów.
p=payload(order);new=copy.deepcopy(p['items'][0]['components'][1]);new.pop('id');new['features']=[];new['name']='Nowa wkładka';p['items'][0]['components'].append(new)
newitem=copy.deepcopy(p['items'][0]);newitem.pop('id');newitem['features']=[];newitem['components']=[copy.deepcopy(new)];newitem['name']='Nowy produkt';p['items'].append(newitem);admin.call('update_order',p)
final=admin.call('order&id='+str(order));assert len(final['items'])==2 and len(final['items'][0]['components'])==3
# Zachowanie nieaktywnych wpisów w istniejącym zleceniu i migawek papieru.
cat=admin.call('dictionary_catalog');paper=next(p for p in cat['categories']['paper_variants']['entries'] if p['id']==c['paper_variant_id']);snapshot=c['paper_description'];gsm=c['grammage_gsm']
admin.call('dictionary_save',{'category':'paper_variants',**paper,'name':'TEST zmieniony słownik papieru','grammage_gsm':300,'is_active':0})
try:
 p=payload(order);admin.call('update_order',p);c=admin.call('order&id='+str(order))['items'][0]['components'][0];assert c['paper_description']==snapshot and c['grammage_gsm']==gsm
finally:admin.call('dictionary_save',{'category':'paper_variants',**paper})
p=payload(order);admin.call('update_order',p,status=403,csrf=False)
worker=Client()
# Rola produkcyjna ma podgląd, ale nie może edytować zamówień.
pwd=secrets.token_urlsafe(18);login='edit-'+secrets.token_hex(4);admin.call('create_user',{'login':login,'display_name':'TEST brak edycji','password':pwd,'role_ids':[next(r['id'] for r in d['roles'] if r['code']=='graphic')],'work_center_ids':[]});worker.login(login,pwd);worker.call('update_order',p,status=403)
print('OK: edycja nagłówka, produktów, części, informacji, farb i cech; dodawanie; zachowanie raportów, powiązań i migawek; rollback, konflikty i uprawnienia.')
