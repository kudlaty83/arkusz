#!/usr/bin/env python3
"""Edycja etapów, graf zależności, rewizje i kopiowanie szablonów do części."""
import copy,secrets
from smoke import admin,b,d,order,first,second,base_task,task,report,Client,item,c,uid

def payload(id,**changes):return {**admin.call('task&id='+str(id)),**changes}
def save(p):
 v=admin.call('preview_task_update',p);return admin.call('update_task',{**p,'confirmation_token':v['confirmation_token']})
def order_detail(id=order):return admin.call('order&id='+str(id))
# Podgląd i potwierdzenie; instrukcje nie zerują wykonania zakończonego etapu.
before=order_detail();p=payload(first,instructions='TEST nowe instrukcje',due_at='2027-04-10T10:00',planned_start_at='2027-04-09T10:00');admin.call('update_task',p,status=409)
v=admin.call('preview_task_update',p);assert order_detail()==before
assert task(first)['version']==p['version'] and any('instrukcje' in x for x in v['changes'])
admin.call('update_task',{**p,'confirmation_token':v['confirmation_token']});assert task(first)['status']=='done' and int(task(first)['good_quantity'])==10 and task(first)['plan_revision']==1
assert task(first)['due_at']=='2027-04-10 10:00:00';assert order_detail()['reports']==before['reports'];assert order_detail()['task_changes']
admin.call('preview_task_update',p,status=409)
# Cykl i samopowiązanie są odrzucane bez zmiany grafu lub danych.
for deps in [[first],[second]]:admin.call('preview_task_update',payload(first,depends_on=deps),status=422)
admin.call('preview_task_update',payload(first,planned_start_at='2027-04-11T10:00',due_at='2027-04-10T10:00'),status=422)
# Zmiana operacji tworzy nową rewizję dla etapu i następników; zakończone zamówienie wraca do produkcji.
old_reports=order_detail()['reports'];op=next(o['id'] for o in d['operation_types'] if o['id']!=base_task['operation_type_id']);save(payload(first,operation_type_id=op,parameter_notes='Parametry nowej operacji'))
assert task(first)['plan_revision']==2 and task(first)['status']=='blocked' and int(task(first)['good_quantity'])==0
assert task(second)['plan_revision']==2 and task(second)['status']=='blocked'
assert order_detail()['reports']==old_reports
assert next(o for o in admin.call('orders') if o['id']==order)['status_code']=='production'
# Raport po podglądzie oraz edycja innego etapu unieważniają potwierdzenie.
p=payload(first,planned_quantity=15);v=admin.call('preview_task_update',p);report(first,'in_progress',1)
admin.call('update_task',{**p,'confirmation_token':v['confirmation_token']},status=409)
save(payload(first,planned_quantity=15));assert int(task(first)['good_quantity'])==1
p=payload(first,instructions='Jeszcze inna instrukcja');v=admin.call('preview_task_update',p);save(payload(second,instructions='Inna edycja'))
admin.call('update_task',{**p,'confirmation_token':v['confirmation_token']},status=409)
# Zwiększenie planu etapu zakończonego zachowuje jego dobre sztuki.
report(first,'done',14);save(payload(first,planned_quantity=20));assert task(first)['status']=='blocked' and int(task(first)['good_quantity'])==15 and task(first)['plan_revision']==2
# Anulowany etap pozostaje historyczny, a niewłaściwy wykonawca nie przechodzi walidacji.
x=admin.call('create_task',{**base_task,'assignee_ids':[]})['id'];report(x,'cancelled');admin.call('preview_task_update',payload(x),status=422)
foreign_center=next(w['id'] for w in d['work_centers'] if w['id']!=base_task['work_center_id']);pwd=secrets.token_urlsafe(18);login='stage-'+secrets.token_hex(4)
worker_id=admin.call('create_user',{'login':login,'display_name':'TEST etapy','password':pwd,'role_ids':[next(r['id'] for r in d['roles'] if r['code']=='graphic')],'work_center_ids':[foreign_center]})['id']
admin.call('preview_task_update',payload(first,assignee_ids=[worker_id]),status=422)
worker=Client();worker.login(login,pwd);worker.call('task&id='+str(first),status=403);worker.call('preview_task_update',payload(first),status=403)
# Nowa część ma jawny rodzaj, a szablon może ograniczać produkt i technikę druku.
kind=next(k['id'] for k in d['component_types'] if k['code']=='cover');newitem=copy.deepcopy(item);newitem['components'][0]['component_type_id']=kind
new_order=admin.call('create_order',{'customer_id':c,'title':'TEST szablony','due_at':'2027-05-15T12:00','items':[newitem]})['id'];detail=order_detail(new_order);component=detail['items'][0]['components'][0];other_component=detail['items'][0]['components'][1]
step={'operation_type_id':base_task['operation_type_id'],'work_center_id':base_task['work_center_id'],'quantity_mode':'per_copy','quantity_value':'1.25','quantity_unit':'ark.','days_before_due':2,'depends_on_previous':0,'instructions':'TEST druk','parameter_notes':'CMYK'}
template={'name':'TEST Szablon '+secrets.token_hex(4),'component_type_id':kind,'product_type_id':newitem['product_type_id'],'print_technique':'offset','description':'Plan okładki','is_active':1,'steps':[step,{**step,'quantity_mode':'fixed','quantity_value':3,'quantity_unit':'próby','days_before_due':1,'depends_on_previous':1}]}
tid=admin.call('save_template',template)['id'];saved=admin.call('template&id='+str(tid));assert len(saved['steps'])==2
assert any(t['id']==tid for t in admin.call('templates&component_id='+str(component['id']))['entries'])
assert not any(t['id']==tid for t in admin.call('templates&component_id='+str(other_component['id']))['entries'])
apply={'component_id':component['id'],'template_id':tid,'assignees':{'1':[uid],'2':[]}}
old_tasks=admin.call('tasks');v=admin.call('preview_apply_template',apply);assert admin.call('tasks')==old_tasks and any('13 ark.' in x for x in v['changes'])
admin.call('apply_template',apply,status=409)
# Zmiana szablonu po podglądzie jest wykrywana. Późniejsza zmiana nie przepisuje kopii.
admin.call('save_template',{**saved,'description':'Nowy opis'});admin.call('apply_template',{**apply,'confirmation_token':v['confirmation_token']},status=409)
v=admin.call('preview_apply_template',apply);result=admin.call('apply_template',{**apply,'confirmation_token':v['confirmation_token']});a,z=result['task_ids']
assert task(a)['planned_quantity']==13 and task(z)['planned_quantity']==3
assert task(a)['due_at']=='2027-05-13 12:00:00' and task(z)['due_at']=='2027-05-14 12:00:00'
assert admin.call('task&id='+str(z))['depends_on']==[a]
assert admin.call('task&id='+str(a))['assignee_ids']==[uid]
assert all(task(i)['component_id']==component['id'] for i in [a,z])
copy_before=[task(i) for i in [a,z]];updated=admin.call('template&id='+str(tid));updated['steps'][0]['quantity_value']=5;admin.call('save_template',updated);assert [task(i) for i in [a,z]]==copy_before
admin.call('save_template',updated,status=409)
admin.call('preview_apply_template',apply,status=409);admin.call('preview_apply_template',{**apply,'component_id':other_component['id']},status=422)
# Nieaktywny szablon i nieprawidłowe ilości nie tworzą zadań, a CSRF i role obowiązują.
current=admin.call('template&id='+str(tid));admin.call('save_template',{**current,'is_active':0});admin.call('preview_apply_template',apply,status=422)
admin.call('save_template',{**template,'steps':[{**step,'quantity_mode':'fixed','quantity_value':1.5}]},status=422)
admin.call('save_template',{**template,'steps':{'x':step}},status=422);admin.call('save_template',{**template,'steps':['błędny etap']},status=422)
admin.call('save_template',template,status=403,csrf=False);admin.call('preview_task_update',payload(first),status=403,csrf=False)
worker.call('templates',status=403);worker.call('save_template',template,status=403);worker.call('preview_apply_template',apply,status=403)
# Utworzenie następnego etapu nie może użyć samego siebie jako poprzednika; graf sprawdzany jest także przy tworzeniu.
new=admin.call('create_task',{**base_task,'depends_on':[],'assignee_ids':[]})['id'];admin.call('preview_task_update',payload(new,depends_on=[new]),status=422)
print('OK: edycja etapów i terminów, podgląd, konflikty i cykle, zachowanie raportów, rewizje i następnicy, dopasowanie szablonu do części, przeliczenia i terminy, przydziały, wersje, brak duplikowania, niezależność kopii, CSRF i role.')
