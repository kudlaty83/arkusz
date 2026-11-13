#!/usr/bin/env python3
"""Odtworzenie schematu 5 i weryfikacja migracji 0006 na osobnej bazie."""
import argparse,copy,json,os,pathlib,secrets,shlex,subprocess
p=argparse.ArgumentParser();p.add_argument('--engine',default='podman compose');p.add_argument('--project',default='arkusz-fixes-test');args=p.parse_args()
if args.project=='arkusz':raise SystemExit('Wymagana osobna instalacja testowa.')
command=shlex.split(args.engine)+['-p',args.project]
def compose(*parts):return subprocess.check_output(command+list(parts),stderr=subprocess.PIPE)
config=json.loads(compose('config','--format','json'));root=pathlib.Path(config['services']['setup']['environment']['APP_DATA_HOST_PATH'])
if root.resolve()==(pathlib.Path.cwd()/'app_data').resolve():raise SystemExit('Nie wykonuj testu na instalacji użytkowej.')
from smoke import admin,b,d,c,item,task,report,uid

def php(code):return compose('exec','-T','web','php','-r','require "/var/www/db.php";'+code)
def sql(query):return php('db()->exec('+json.dumps(query,ensure_ascii=False)+');')
def query(q):return json.loads(php('echo json_encode(rows('+json.dumps(q)+'));'))
def order():return admin.call('create_order',{'customer_id':c,'title':'TEST migracja '+secrets.token_hex(4),'items':[copy.deepcopy(item)]})['id']
def add(o,deps=None):
 it=admin.call('order&id='+str(o))['items'][0]
 return admin.call('create_task',{'order_item_id':it['id'],'component_id':it['components'][0]['id'],'operation_type_id':d['operation_types'][0]['id'],'work_center_id':d['work_centers'][0]['id'],'sequence_number':10,'planned_quantity':10,'quantity_unit':'szt.','depends_on':deps or [],'assignee_ids':[uid]})['id']
o=order();active=add(o);finished=add(o);report(finished,'in_progress');report(finished,'done',10)
branch_order=order();root_task=add(branch_order);middle=add(branch_order,[root_task]);last=add(branch_order,[middle])
report(root_task,'in_progress');report(root_task,'done',10);report(middle,'in_progress');report(middle,'done',10)
kind=next(k['id'] for k in d['component_types'] if k['code']=='cover');draft=copy.deepcopy(item);draft['components'][0]['component_type_id']=kind
template_order=admin.call('create_order',{'customer_id':c,'title':'TEST migracja szablonu','items':[draft]})['id'];comp=admin.call('order&id='+str(template_order))['items'][0]['components'][0]['id']
step={'operation_type_id':d['operation_types'][0]['id'],'work_center_id':d['work_centers'][0]['id'],'quantity_mode':'fixed','quantity_value':3,'quantity_unit':'próby','days_before_due':0,'depends_on_previous':0}
template=admin.call('save_template',{'name':'TEST migracja '+secrets.token_hex(4),'component_type_id':kind,'steps':[step,{**step,'quantity_mode':'per_copy','quantity_value':1.25},{**step,'quantity_mode':'fixed','quantity_value':3}]})['id']
data={'component_id':comp,'template_id':template};v=admin.call('preview_apply_template',data);fixed,ratio,edited=admin.call('apply_template',{**data,'confirmation_token':v['confirmation_token']})['task_ids']
data=admin.call('task&id='+str(edited));data['planned_quantity']=4;v=admin.call('preview_task_update',data);admin.call('update_task',{**data,'confirmation_token':v['confirmation_token']})
current=admin.call('template&id='+str(template));current['steps'][0]['quantity_value']=9;admin.call('save_template',current)
# Symulacja starych niespójnych statusów oraz stanu struktury 1.3.0.
cancelled=next(s['id'] for s in d['order_statuses'] if s['code']=='cancelled')
sql(f"UPDATE orders SET status_id={cancelled} WHERE id={o}")
sql(f"UPDATE production_tasks SET status='cancelled' WHERE id={root_task}")
quantities=query('SELECT id,planned_quantity FROM production_tasks ORDER BY id');reports=query('SELECT * FROM production_reports ORDER BY id')
sql('ALTER TABLE production_tasks DROP COLUMN quantity_mode,DROP COLUMN quantity_base_quantity,DROP COLUMN quantity_base_copies,DROP COLUMN quantity_review_required')
sql('DROP TABLE login_attempt_limits');sql('DELETE FROM schema_migrations WHERE version=6')
compose('exec','-T','web','php','/var/www/migrate.php')
assert query('SELECT id,planned_quantity FROM production_tasks ORDER BY id')==quantities
assert query('SELECT * FROM production_reports ORDER BY id')==reports
assert task(active)['status']=='cancelled' and task(finished)['status']=='done'
assert task(middle)['status']=='done' and task(last)['status']=='cancelled'
assert task(active)['quantity_mode']=='legacy' and int(task(active)['quantity_review_required'])==1
assert task(fixed)['quantity_mode']=='fixed' and int(task(fixed)['quantity_base_quantity'])==3
assert task(ratio)['quantity_mode']=='per_copy' and int(task(ratio)['quantity_base_quantity'])==12500 and int(task(ratio)['quantity_base_copies'])==10000
assert task(edited)['quantity_mode']=='legacy' and int(task(edited)['quantity_review_required'])==1
history=query(f"SELECT * FROM task_status_history WHERE task_id IN ({active},{last}) AND reason LIKE 'Automatyczna naprawa 1.4.0:%'");assert len(history)==2
before=query('SELECT * FROM task_status_history ORDER BY id');compose('exec','-T','web','php','/var/www/migrate.php');assert query('SELECT * FROM task_status_history ORDER BY id')==before
print('OK: migracja 0006 naprawia anulowanie także przez zakończony etap, zachowuje ilości i raporty, odtwarza historyczne szablony, oznacza ręczne zmiany i jest idempotentna.')
