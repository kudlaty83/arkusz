<?php
declare(strict_types=1);
require dirname(__DIR__).'/db.php';
require_once dirname(__DIR__).'/version.php';
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict','secure'=>!empty($_SERVER['HTTPS'])]);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function fail(string $message,int $status=422): never { http_response_code($status); echo json_encode(['error'=>$message],JSON_UNESCAPED_UNICODE); exit; }
function output(mixed $data): never { echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit; }
function text(array $a,string $k,int $max=255,bool $required=true): string {
 $s=trim((string)($a[$k]??'')); if(($required && $s==='') || strlen($s)>$max) fail('Nieprawidłowe pole: '.$k); return $s;
}
function num(array $a,string $k,float $min=0,float $max=100000000): float {
 $v=$a[$k]??null; if(!is_numeric($v)||!is_finite((float)$v)||(float)$v<$min||(float)$v>$max) fail('Nieprawidłowa liczba: '.$k); return (float)$v;
}
function integer(array $a,string $k,int $min=1,int $max=100000000): int {
 $v=num($a,$k,$min,$max); if(floor($v)!=$v) fail('Wymagana liczba całkowita: '.$k); return (int)$v;
}
function reference(string $table,int $id): array { return one("SELECT * FROM $table WHERE id=?",[$id])??fail('Nie znaleziono rekordu: '.$table,404); }
function allowed(string $p): bool { return in_array($p,$_SESSION['permissions']??[],true); }
function permit(string $p): void { if(!allowed($p)) fail('Brak uprawnień do tej operacji.',403); }
function feature(int $item,?int $component,array $f): int {
 $prefix=$component?'component':'item';
 $existing=null;
 if(isset($f['id'])){
  $existing=reference($prefix.'_features',integer($f,'id'));
  if((int)$existing['is_archived']||(int)$existing[$component?'component_id':'order_item_id']!==($component??$item))fail('Cecha nie należy do edytowanego produktu lub części.');
 }
 $type=reference('feature_types',integer($f,'feature_type_id'));
 if((!$type['is_active']&&(!$existing||(int)$existing['feature_type_id']!==(int)$type['id'])) || !in_array($type['scope'],[$component?'component':'item','both'],true)) fail('Cecha nie pasuje do wybranego zakresu.');
 $prefix=$component?'component':'item';
 if($existing){
  $id=(int)$existing['id'];
  if((int)$existing['feature_type_id']!==(int)$type['id']){
   run("UPDATE {$prefix}_features SET is_archived=1 WHERE id=?",[$id]);
   $newId=insert($prefix.'_features',[$component?'component_id':'order_item_id'=>$component??$item,'feature_type_id'=>$type['id'],'notes'=>text($f,'notes',5000,false)]);
   run("UPDATE task_{$prefix}_features l JOIN production_tasks t ON t.id=l.task_id SET l.{$prefix}_feature_id=? WHERE l.{$prefix}_feature_id=? AND t.status<>'cancelled'",[$newId,$id]);
   $id=$newId;
  }
  run("UPDATE {$prefix}_features SET feature_type_id=?,notes=? WHERE id=?",[$type['id'],text($f,'notes',5000,false),$id]);
  run("DELETE FROM {$prefix}_feature_values WHERE {$prefix}_feature_id=?",[$id]);
 }else $id=insert($prefix.'_features',[$component?'component_id':'order_item_id'=>$component??$item,'feature_type_id'=>$type['id'],'notes'=>text($f,'notes',5000,false)]);
 $values=$f['values']??[];
 foreach(rows('SELECT * FROM feature_parameter_definitions WHERE feature_type_id=?',[$type['id']]) as $def){
  $value=$values[$def['code']]??null;
  if($value===null || $value===''){ if($def['is_required']) fail('Wymagany parametr: '.$def['name']); continue; }
  $data=[$prefix.'_feature_id'=>$id,'parameter_definition_id'=>$def['id']];
  if($def['value_type']==='number') { if(!is_numeric($value)||abs((float)$value)>9999999999) fail('Nieprawidłowy parametr liczbowy.'); $data['value_number']=$value; }
  elseif($def['value_type']==='boolean') { if(!in_array($value,[true,false,0,1,'0','1'],true)) fail('Nieprawidłowy parametr logiczny.'); $data['value_boolean']=(int)$value; }
  else { if(!is_string($value)||strlen($value)>5000) fail('Nieprawidłowy parametr tekstowy.'); $data['value_text']=$value; }
  insert($prefix.'_feature_values',$data);
 }
 return $id;
}
require dirname(__DIR__).'/dictionaries.php';
require dirname(__DIR__).'/orders.php';
require dirname(__DIR__).'/order-plan.php';
require dirname(__DIR__).'/customers.php';
require dirname(__DIR__).'/users.php';
function taskAccess(array $task): void {
 if(allowed('tasks.manage')) return;
 if(!one('SELECT 1 FROM task_assignments WHERE task_id=? AND user_id=?',[$task['id'],$_SESSION['uid']])) fail('Możesz raportować tylko przydzielone zadania.',403);
}
try {
 try{assertSchemaCompatible();}catch(Throwable){fail('Schemat bazy wymaga migracji lub interwencji administratora.',503);}
 $action=$_GET['action']??'bootstrap'; $method=$_SERVER['REQUEST_METHOD'];
 $data=[];
 if($method==='POST') {
  if((int)($_SERVER['CONTENT_LENGTH']??0)>1048576) fail('Za duże żądanie.',413);
  try { $data=json_decode(file_get_contents('php://input'),true,64,JSON_THROW_ON_ERROR); } catch(Throwable) { fail('Niepoprawny JSON.'); }
  if(!is_array($data)) fail('Niepoprawne dane.');
 }
 if($action==='login' && $method==='POST') {
  $attempts=$_SESSION['login_attempts']??[]; $attempts=array_values(array_filter($attempts,fn($t)=>$t>time()-300));
  if(count($attempts)>=10) fail('Zbyt wiele prób. Spróbuj za pięć minut.',429);
  $u=one('SELECT * FROM users WHERE login=? AND is_active=1',[text($data,'login',100)]);
  if(!$u || !password_verify((string)($data['password']??''),$u['password_hash'])) { $attempts[]=time(); $_SESSION['login_attempts']=$attempts; fail('Nieprawidłowy login lub hasło.',401); }
  session_regenerate_id(true); $_SESSION=['uid'=>(int)$u['id'],'auth_version'=>(int)$u['auth_version'],'csrf'=>bin2hex(random_bytes(32))]; output(['ok'=>true]);
 }
 if(empty($_SESSION['uid'])) fail('Zaloguj się, aby kontynuować.',401);
 $user=one('SELECT id,login,display_name,theme,auth_version FROM users WHERE id=? AND is_active=1',[$_SESSION['uid']]);
 if(!$user||(int)($_SESSION['auth_version']??1)!==(int)$user['auth_version']) {$_SESSION=[];session_destroy();fail('Sesja wygasła lub konto zostało wyłączone. Zaloguj się ponownie.',401);}
 unset($user['auth_version']);
 $_SESSION['permissions']=array_column(rows('SELECT DISTINCT p.code FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id JOIN user_roles ur ON ur.role_id=rp.role_id WHERE ur.user_id=?',[$user['id']]),'code');
 if($method==='POST' && !hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??'')) fail('Sesja formularza wygasła. Odśwież stronę.',403);
 if($action==='logout' && $method==='POST') { $_SESSION=[];session_destroy();output(['ok'=>true]); }
 if($action==='version' && $method==='GET') output(versionInfo());
 if($action==='save_theme' && $method==='POST') {
  $theme=$data['theme']??null;
  if(!is_string($theme)||!in_array($theme,['green','beige','gray','black','beige_light'],true))fail('Nieznany motyw graficzny.');
  run('UPDATE users SET theme=? WHERE id=?',[$theme,$user['id']]);
  output(['theme'=>$theme]);
 }
 if($action==='dictionary_save' && $method==='POST') output(['id'=>dictionarySave($data)]);
 if($action==='dictionary_catalog' && $method==='GET') {
  permit('users.manage');
  $catalog=[];foreach(dictionaryDefinitions() as $table=>$definition)$catalog[$table]=array_merge($definition,['entries'=>rows("SELECT * FROM $table ORDER BY id")]);
  output(['categories'=>$catalog,'permissions'=>rows('SELECT id,code,name FROM permissions ORDER BY id'),'role_permissions'=>rows('SELECT * FROM role_permissions')]);
 }
 if($action==='bootstrap' && $method==='GET') {
  permit('orders.view');
  $dict=[]; foreach(['product_types','paper_types','paper_variants','order_statuses','work_centers','operation_types','feature_types','feature_parameter_definitions','roles'] as $table) $dict[$table]=rows("SELECT * FROM $table".($table==='order_statuses'?' ORDER BY sort_order,id':' ORDER BY id'));
  output(['versions'=>versionInfo(),'user'=>$user,'permissions'=>$_SESSION['permissions'],'csrf'=>$_SESSION['csrf'],'dictionaries'=>$dict,
   'users'=>rows('SELECT u.id,u.display_name,u.login,GROUP_CONCAT(r.name SEPARATOR ", ") roles FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id WHERE u.is_active=1 GROUP BY u.id,u.display_name,u.login'),
   'memberships'=>rows('SELECT * FROM user_work_centers')]);
 }
 if($action==='orders' && $method==='GET') {
  permit('orders.view');
  output(rows("SELECT o.*,s.name status_name,s.code status_code,s.is_terminal status_terminal, (SELECT COUNT(*) FROM order_items i WHERE i.order_id=o.id AND i.is_archived=0) item_count,
   (SELECT COUNT(*) FROM production_tasks t JOIN order_items i ON i.id=t.order_item_id WHERE i.order_id=o.id AND t.status<>'cancelled') task_count,
   (SELECT COUNT(*) FROM production_tasks t JOIN order_items i ON i.id=t.order_item_id WHERE i.order_id=o.id AND t.status='done') done_count,
   (SELECT SUM(i.agreed_total_net) FROM order_items i WHERE i.order_id=o.id AND i.is_archived=0) total_net FROM orders o JOIN order_statuses s ON s.id=o.status_id ORDER BY o.id DESC"));
 }
 if($action==='tasks' && $method==='GET') {
  permit('orders.view');
  output(rows("SELECT t.*,i.name item_name,o.id order_id,o.order_number,o.title order_title,w.name center_name,c.name component_name,
   (SELECT GROUP_CONCAT(u.display_name SEPARATOR ', ') FROM task_assignments a JOIN users u ON u.id=a.user_id WHERE a.task_id=t.id) assignees,
   (SELECT GROUP_CONCAT(a.user_id) FROM task_assignments a WHERE a.task_id=t.id) assignee_ids,
   (SELECT COALESCE(SUM(r.good_quantity),0) FROM production_reports r WHERE r.task_id=t.id AND r.plan_revision=t.plan_revision) good_quantity,
   (SELECT COUNT(*) FROM task_dependencies d JOIN production_tasks p ON p.id=d.depends_on_task_id WHERE d.task_id=t.id AND p.status<>'done') waiting_count
   FROM production_tasks t JOIN order_items i ON i.id=t.order_item_id JOIN orders o ON o.id=i.order_id LEFT JOIN work_centers w ON w.id=t.work_center_id LEFT JOIN item_components c ON c.id=t.component_id ORDER BY t.due_at IS NULL,t.due_at,t.sequence_number"));
 }
 if($action==='order' && $method==='GET') {
  permit('orders.view'); $order=reference('orders',integer($_GET,'id'));
  $items=rows('SELECT * FROM order_items WHERE order_id=? AND is_archived=0 ORDER BY line_number',[$order['id']]);
  foreach($items as &$i) {
   $i['components']=rows('SELECT * FROM item_components WHERE order_item_id=? AND is_archived=0 ORDER BY sort_order,id',[$i['id']]);
   foreach($i['components'] as &$c) $c['inks']=rows('SELECT * FROM component_inks WHERE component_id=? ORDER BY side,ink_number',[$c['id']]); unset($c);
   $i['features']=rows("SELECT f.*,ft.name feature_name,'item' scope,NULL component_name FROM item_features f JOIN feature_types ft ON ft.id=f.feature_type_id WHERE f.order_item_id=? AND f.is_archived=0",[$i['id']]);
   $cf=rows("SELECT f.*,ft.name feature_name,'component' scope,c.name component_name FROM component_features f JOIN feature_types ft ON ft.id=f.feature_type_id JOIN item_components c ON c.id=f.component_id WHERE c.order_item_id=? AND c.is_archived=0 AND f.is_archived=0",[$i['id']]);
   $i['features']=array_merge($i['features'],$cf);
   foreach($i['features'] as &$f){ $prefix=$f['scope']; $f['values']=rows("SELECT d.id,d.code,d.value_type,d.name,d.unit,v.value_text,v.value_number,v.value_boolean FROM {$prefix}_feature_values v JOIN feature_parameter_definitions d ON d.id=v.parameter_definition_id WHERE v.{$prefix}_feature_id=?",[$f['id']]); } unset($f);
  } unset($i);
  output(['order'=>$order,'items'=>$items,
   'changes'=>array_map(static function($change){$change['changes']=json_decode($change['changes'],true,512,JSON_THROW_ON_ERROR);return $change;},rows('SELECT h.id,h.changed_at,h.changes,u.display_name FROM order_change_history h JOIN users u ON u.id=h.changed_by WHERE h.order_id=? ORDER BY h.id DESC',[$order['id']])),
   'history'=>rows('SELECT h.*,s.name status_name,u.display_name FROM order_status_history h JOIN order_statuses s ON s.id=h.new_status_id JOIN users u ON u.id=h.changed_by WHERE h.order_id=? ORDER BY h.id DESC',[$order['id']]),
   'reports'=>rows('SELECT r.*,t.operation_name,u.display_name FROM production_reports r JOIN production_tasks t ON t.id=r.task_id JOIN order_items i ON i.id=t.order_item_id JOIN users u ON u.id=r.reported_by WHERE i.order_id=? ORDER BY r.id DESC',[$order['id']]),
   'task_history'=>rows('SELECT h.*,t.operation_name,u.display_name FROM task_status_history h JOIN production_tasks t ON t.id=h.task_id JOIN order_items i ON i.id=t.order_item_id JOIN users u ON u.id=h.changed_by WHERE i.order_id=? ORDER BY h.id DESC',[$order['id']])]);
 }
 if($action==='customers' && $method==='GET')output(customerPage($_GET));
 if($action==='customer' && $method==='GET'){permit('orders.view');output(reference('customers',integer($_GET,'id')));}
 if(in_array($action,['customer','update_customer'],true) && $method==='POST')output(['id'=>saveCustomer($data,$action==='update_customer')]);
 if($action==='users' && $method==='GET')output(userPage($_GET));
 if($action==='user' && $method==='GET'){permit('users.manage');output(userDetails(integer($_GET,'id')));}
 if(in_array($action,['create_user','update_user'],true) && $method==='POST')output(['id'=>saveUser($data,$action==='update_user',(int)$user['id'])]);
 if(in_array($action,['change_password','reset_password'],true) && $method==='POST'){
  changePassword($data,(int)$user['id'],$action==='reset_password');output(['ok'=>true,'csrf'=>$_SESSION['csrf']]);
 }
 if($action==='preview_order_update' && $method==='POST')output(saveOrder($data,true,(int)$user['id'],true));
 if(in_array($action,['create_order','update_order'],true) && $method==='POST') output(['id'=>saveOrder($data,$action==='update_order',(int)$user['id'])]);
 if($action==='order_status' && $method==='POST') {
  permit('orders.edit');db()->beginTransaction();$id=integer($data,'id');$o=one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id])??fail('Brak zamówienia.',404);
  if((int)$o['version']!==integer($data,'version')) fail('Zamówienie zmieniła inna osoba. Odśwież widok.',409);
  $s=reference('order_statuses',integer($data,'status_id'));if(!$s['is_active'])fail('Nieaktywny status.');
  if((in_array($s['code'],['ready','delivered'],true)||($s['is_terminal']&&$s['code']!=='cancelled')) && one("SELECT t.id FROM production_tasks t JOIN order_items i ON i.id=t.order_item_id WHERE i.order_id=? AND t.status NOT IN ('done','cancelled') LIMIT 1",[$id])) fail('Najpierw zakończ zadania produkcyjne.');
  run('UPDATE orders SET status_id=?,completed_at=?,version=version+1 WHERE id=?',[$s['id'],(in_array($s['code'],['ready','delivered'],true)||($s['is_terminal']&&$s['code']!=='cancelled'))?gmdate('Y-m-d H:i:s'):null,$id]);
  insert('order_status_history',['order_id'=>$id,'old_status_id'=>$o['status_id'],'new_status_id'=>$s['id'],'changed_by'=>$user['id'],'reason'=>text($data,'reason',5000,false)]);db()->commit();output(['ok'=>true]);
 }
 if($action==='create_task' && $method==='POST') {
  permit('tasks.manage');db()->beginTransaction();
  $item=reference('order_items',integer($data,'order_item_id'));
  one('SELECT id FROM orders WHERE id=? FOR UPDATE',[$item['order_id']]);
  $item=one('SELECT * FROM order_items WHERE id=? FOR UPDATE',[$item['id']])??fail('Brak produktu.',404);
  if((int)$item['is_archived'])fail('Produkt został usunięty z bieżącej specyfikacji.');
  $component=!empty($data['component_id'])?(one('SELECT * FROM item_components WHERE id=? FOR UPDATE',[integer($data,'component_id')])??fail('Brak części.',404)):null;
  if($component&&((int)$component['is_archived']||(int)$component['order_item_id']!==(int)$item['id']))fail('Część nie należy do produktu.');
  $op=activeReference('operation_types',integer($data,'operation_type_id'));$center=activeReference('work_centers',integer($data,'work_center_id'));
  $task=insert('production_tasks',['order_item_id'=>$item['id'],'component_id'=>$component['id']??null,'operation_type_id'=>$op['id'],'operation_name'=>$op['name'],'sequence_number'=>integer($data,'sequence_number',1,65535),'status'=>'pending','work_center_id'=>$center['id'],'planned_quantity'=>integer($data,'planned_quantity'),'quantity_unit'=>text($data,'quantity_unit',30),'due_at'=>reference('orders',(int)$item['order_id'])['due_at'],'instructions'=>text($data,'instructions',10000,false)]);
  foreach(array_unique($data['depends_on']??[]) as $dep){ $parent=reference('production_tasks',(int)$dep);if((int)$parent['order_item_id']!==(int)$item['id'])fail('Poprzednik musi należeć do tego samego produktu.');insert('task_dependencies',['task_id'=>$task,'depends_on_task_id'=>$parent['id']]); }
  foreach(['item','component'] as $scope) foreach(array_unique($data[$scope.'_feature_ids']??[]) as $fid){ $f=reference($scope.'_features',(int)$fid);if((int)$f['is_archived'])fail('Cecha została usunięta.'); if($scope==='item'&&(int)$f['order_item_id']!==(int)$item['id'])fail('Cecha innego produktu.');if($scope==='component'&&(!$component||(int)$f['component_id']!==(int)$component['id']))fail('Cecha innej części.');insert('task_'.$scope.'_features',['task_id'=>$task,$scope.'_feature_id'=>$fid]); }
  foreach(array_unique($data['assignee_ids']??[]) as $uid){ if(!one('SELECT u.id FROM users u JOIN user_work_centers c ON c.user_id=u.id WHERE u.id=? AND u.is_active=1 AND c.work_center_id=?',[(int)$uid,$center['id']]))fail('Pracownik nie jest przypisany do tego stanowiska.');insert('task_assignments',['task_id'=>$task,'user_id'=>(int)$uid,'assigned_by'=>$user['id']]); }
  insert('task_status_history',['task_id'=>$task,'new_status'=>'pending','event_type'=>'created','changed_by'=>$user['id'],'reason'=>'Utworzenie zadania i przydział wykonawców']);db()->commit();output(['id'=>$task]);
 }
 if($action==='assign_task' && $method==='POST'){
  permit('tasks.manage');db()->beginTransaction();$t=one('SELECT * FROM production_tasks WHERE id=? FOR UPDATE',[integer($data,'id')])??fail('Brak zadania.',404);
  if((int)$t['version']!==integer($data,'version'))fail('Zadanie zmieniono. Odśwież widok.',409);
  $ids=array_unique($data['assignee_ids']??[]);$names=[];
  foreach($ids as $uid){$u=one('SELECT u.id,u.display_name FROM users u JOIN user_work_centers c ON c.user_id=u.id WHERE u.id=? AND u.is_active=1 AND c.work_center_id=?',[(int)$uid,$t['work_center_id']]);if(!$u)fail('Pracownik spoza stanowiska.');$names[]=$u['display_name'];}
  run('DELETE FROM task_assignments WHERE task_id=?',[$t['id']]);foreach($ids as $uid)insert('task_assignments',['task_id'=>$t['id'],'user_id'=>(int)$uid,'assigned_by'=>$user['id']]);
  run('UPDATE production_tasks SET version=version+1 WHERE id=?',[$t['id']]);insert('task_status_history',['task_id'=>$t['id'],'old_status'=>$t['status'],'new_status'=>$t['status'],'event_type'=>'assignment','changed_by'=>$user['id'],'reason'=>'Wykonawcy: '.implode(', ',$names)]);db()->commit();output(['ok'=>true]);
 }
 if($action==='report' && $method==='POST') {
  permit('tasks.report');db()->beginTransaction();$t=one('SELECT * FROM production_tasks WHERE id=? FOR UPDATE',[integer($data,'task_id')])??fail('Brak zadania.',404);taskAccess($t);
  if((int)$t['version']!==integer($data,'version'))fail('Zadanie zostało zmienione. Odśwież widok.',409);
  $status=$data['status_after']??'';$transitions=['pending'=>['in_progress','blocked','cancelled'],'ready'=>['in_progress','blocked','cancelled'],'in_progress'=>['in_progress','blocked','done','cancelled'],'blocked'=>['blocked','in_progress','cancelled'],'done'=>[],'cancelled'=>[]];
  if(!in_array($status,$transitions[$t['status']]??[],true))fail('Niedozwolona zmiana statusu. Najpierw rozpocznij zadanie.');
  if($status==='cancelled')permit('tasks.manage');
  if(in_array($status,['in_progress','done'],true)&&one("SELECT d.task_id FROM task_dependencies d JOIN production_tasks p ON p.id=d.depends_on_task_id WHERE d.task_id=? AND p.status<>'done'",[$t['id']]))fail('Najpierw zakończ zadania poprzedzające.');
  $good=integer($data,'good_quantity',0);$waste=integer($data,'waste_quantity',0);$minutes=integer($data,'work_minutes',0,100000);
  $notes=text($data,'notes',10000,false);if($status==='blocked'&&$notes==='')fail('Opisz przyczynę blokady.');
  $total=(int)one('SELECT COALESCE(SUM(good_quantity),0) qty FROM production_reports WHERE task_id=? AND plan_revision=?',[$t['id'],$t['plan_revision']])['qty']+$good;
  if($status==='done'&&$total<(int)$t['planned_quantity'])fail('Wykonana ilość jest mniejsza od planowanej.');
  insert('production_reports',['task_id'=>$t['id'],'plan_revision'=>$t['plan_revision'],'reported_by'=>$user['id'],'good_quantity'=>$good,'waste_quantity'=>$waste,'work_minutes'=>$minutes,'status_after'=>$status,'notes'=>$notes]);
  run("UPDATE production_tasks SET status=?,version=version+1,started_at=IF(?='in_progress',COALESCE(started_at,UTC_TIMESTAMP()),started_at),finished_at=IF(?='done',UTC_TIMESTAMP(),NULL) WHERE id=?",[$status,$status,$status,$t['id']]);
  insert('task_status_history',['task_id'=>$t['id'],'old_status'=>$t['status'],'new_status'=>$status,'changed_by'=>$user['id'],'reason'=>$notes]);db()->commit();output(['ok'=>true]);
 }
 fail('Nieznana operacja.',404);
} catch(PDOException $e) {
 if(db()->inTransaction())db()->rollBack();error_log($e->getMessage());
 fail($e->getCode()==='23000'?'Dane są powielone lub naruszają powiązania.':'Błąd bazy danych. Sprawdź log serwera.', $e->getCode()==='23000'?409:500);
} catch(Throwable $e) { if(db()->inTransaction())db()->rollBack();error_log((string)$e);fail('Wystąpił błąd serwera.',500); }
