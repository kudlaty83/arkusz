<?php
declare(strict_types=1);
function taskDate(array $data,string $key): ?string {
 $value=text($data,$key,19,false);if($value==='')return null;
 $value=str_replace('T',' ',$value);if(strlen($value)===16)$value.=':00';
 $date=DateTime::createFromFormat('!Y-m-d H:i:s',$value);
 if(!$date||$date->format('Y-m-d H:i:s')!==$value)fail('Nieprawidłowa data: '.$key);
 return $value;
}
function lockTaskOrder(int $itemId): array {
 $item=reference('order_items',$itemId);
 one('SELECT id FROM orders WHERE id=? FOR UPDATE',[$item['order_id']]);
 $snapshot=orderPlanSnapshot((int)$item['order_id']);
 $item=reference('order_items',$itemId);
 if((int)$item['is_archived'])fail('Produkt został wycofany ze specyfikacji.');
 return [$item,$snapshot];
}
function taskDetails(int $id): array {
 $t=reference('production_tasks',$id);
 foreach(['task_assignments'=>'assignee_ids','task_dependencies'=>'depends_on','task_item_features'=>'item_feature_ids','task_component_features'=>'component_feature_ids'] as $table=>$key){
  $column=['task_assignments'=>'user_id','task_dependencies'=>'depends_on_task_id','task_item_features'=>'item_feature_id','task_component_features'=>'component_feature_id'][$table];
  $t[$key]=array_map('intval',array_column(rows("SELECT $column FROM $table WHERE task_id=? ORDER BY $column",[$id]),$column));
 }
 $t['parameter_notes']=json_decode($t['parameters']??'{}',true)['notes']??'';
 return $t;
}
function checkTaskGraph(array $tasks,array $dependencies,int $id,array $parents): void {
 $graph=[];foreach($tasks as $t)$graph[(int)$t['id']]=[];
 foreach($dependencies as $d)if((int)$d['task_id']!==$id)$graph[(int)$d['task_id']][]=(int)$d['depends_on_task_id'];
 $graph[$id]=$parents;$state=[];
 $visit=static function(int $node)use(&$visit,&$state,$graph):void{
  if(($state[$node]??0)===1)fail('Zależności etapów tworzyłyby cykl.');if(($state[$node]??0)===2)return;
  $state[$node]=1;foreach($graph[$node]??[] as $parent)$visit($parent);$state[$node]=2;
 };
 foreach(array_keys($graph) as $node)$visit($node);
}
function taskAssignees(array $ids,int $center,array $retained=[],bool $sameCenter=false): void {
 foreach($ids as $uid){
  if($sameCenter&&in_array($uid,$retained,true))continue;
  if(!one('SELECT u.id FROM users u JOIN user_work_centers c ON c.user_id=u.id WHERE u.id=? AND u.is_active=1 AND c.work_center_id=?',[$uid,$center]))fail('Nowy wykonawca musi być aktywny i przypisany do wybranego stanowiska.');
 }
}
function reopenTaskOrder(array $order,int $userId,string $reason): void {
 $status=reference('order_statuses',(int)$order['status_id']);
 if($status['is_terminal']||$status['code']==='ready'){
  $production=(int)one("SELECT id FROM order_statuses WHERE code='production'")['id'];
  run('UPDATE orders SET status_id=?,completed_at=NULL,version=version+1 WHERE id=?',[$production,$order['id']]);
  insert('order_status_history',['order_id'=>$order['id'],'old_status_id'=>$order['status_id'],'new_status_id'=>$production,'changed_by'=>$userId,'reason'=>$reason]);
 }
}
function planApproval(string $key,array $data,mixed $snapshot,mixed $plan,bool $preview): ?string {
 unset($data['confirmation_token']);$fingerprint=hash('sha256',json_encode([$data,$snapshot,$plan],JSON_THROW_ON_ERROR));
 if($preview){$token=bin2hex(random_bytes(32));$_SESSION['production_approvals'][$key]=['token'=>$token,'fingerprint'=>$fingerprint,'expires'=>time()+900];return $token;}
 $approval=$_SESSION['production_approvals'][$key]??null;
 // Token jest sprawdzany osobno przez wywołującego, zanim zostanie usunięty z danych odcisku.
 if(!$approval||!hash_equals($approval['fingerprint'],$fingerprint)||$approval['expires']<time())fail('Plan zmienił się od podglądu. Sprawdź i zatwierdź aktualne podsumowanie.',409);
 return null;
}
function checkPlanToken(string $key,array $data): void {
 $approval=$_SESSION['production_approvals'][$key]??null;
 if(!$approval||!is_string($data['confirmation_token']??null)||!hash_equals($approval['token'],$data['confirmation_token']))fail('Najpierw sprawdź podsumowanie i zatwierdź zmiany.',409);
}
function saveTask(array $data,int $userId,bool $editing,bool $preview=false): int|array {
 permit('tasks.manage');db()->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');db()->beginTransaction();
 $existing=$editing?reference('production_tasks',integer($data,'id')):null;
 [$item,$snapshot]=lockTaskOrder($editing?(int)$existing['order_item_id']:integer($data,'order_item_id'));
 $old=$editing?taskDetails((int)$existing['id']):null;
 if($old&&((int)$old['version']!==integer($data,'version')))fail('Etap zmieniono. Otwórz edytor ponownie.',409);
 if($old&&$old['status']==='cancelled')fail('Anulowany etap pozostaje w historii. Dodaj nowy etap zamiast go zmieniać.');
 $component=!empty($data['component_id'])?reference('item_components',integer($data,'component_id')):null;
 if($component&&((int)$component['order_item_id']!==(int)$item['id']||(int)$component['is_archived']))fail('Wybierz aktywną część tego produktu.');
 $op=retainedReference('operation_types',integer($data,'operation_type_id'),$old['operation_type_id']??null);
 $center=retainedReference('work_centers',integer($data,'work_center_id'),$old['work_center_id']??null);
 $fields=['component_id'=>$component['id']??null,'operation_type_id'=>$op['id'],'operation_name'=>$old&&(int)$old['operation_type_id']===(int)$op['id']?$old['operation_name']:$op['name'],'work_center_id'=>$center['id'],'sequence_number'=>integer($data,'sequence_number',1,65535),'planned_quantity'=>integer($data,'planned_quantity'),'quantity_unit'=>text($data,'quantity_unit',30),'planned_start_at'=>taskDate($data,'planned_start_at'),'due_at'=>array_key_exists('due_at',$data)?taskDate($data,'due_at'):($snapshot['order']['due_at']??null),'instructions'=>text($data,'instructions',10000,false)];
 if($fields['planned_start_at']&&$fields['due_at']&&$fields['planned_start_at']>$fields['due_at'])fail('Planowany początek musi być przed terminem zakończenia.');
 $params=$old?json_decode($old['parameters']??'{}',true):[];if(!is_array($params))$params=[];$notes=text($data,'parameter_notes',10000,false);if($notes!==''||array_key_exists('notes',$params))$params['notes']=$notes;$fields['parameters']=$params?json_encode($params,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):null;
 $ids=[];foreach(['assignee_ids','depends_on','item_feature_ids','component_feature_ids'] as $key){$ids[$key]=userIds($data,$key);sort($ids[$key]);}
 $tasks=array_values(array_filter($snapshot['tasks'],fn($t)=>(int)$t['order_item_id']===(int)$item['id']));$byId=array_column($tasks,null,'id');
 foreach($ids['depends_on'] as $parent){if(!isset($byId[$parent])||($old&&$parent===(int)$old['id'])||$byId[$parent]['status']==='cancelled')fail('Poprzednik musi być innym, nieanulowanym etapem tego produktu.');}
 $taskId=$old?(int)$old['id']:0;
 checkTaskGraph($tasks,$snapshot['task_dependencies'],$taskId,$ids['depends_on']);
 taskAssignees($ids['assignee_ids'],(int)$center['id'],$old['assignee_ids']??[],$old&&(int)$old['work_center_id']===(int)$center['id']);
 foreach(['item','component'] as $scope)foreach($ids[$scope.'_feature_ids'] as $fid){
  $f=reference($scope.'_features',$fid);$retained=$old&&in_array($fid,$old[$scope.'_feature_ids'],true)&&($scope==='item'||(int)$old['component_id']===(int)($component['id']??0));
  if((int)$f['is_archived']&&!$retained)fail('Nie można dodać wycofanej cechy.');
  if($scope==='item'&&(int)$f['order_item_id']!==(int)$item['id'])fail('Cecha innego produktu.');
  if($scope==='component'&&(!$component||(int)$f['component_id']!==(int)$component['id']))fail('Cecha innej części.');
 }
 if(!$editing){
  $taskId=insert('production_tasks',['order_item_id'=>$item['id'],'status'=>'pending',...$fields]);
  writeTaskLinks($taskId,$ids,$userId);
  insert('task_status_history',['task_id'=>$taskId,'new_status'=>'pending','event_type'=>'created','changed_by'=>$userId,'reason'=>'Utworzenie etapu i przydział wykonawców']);
  reopenTaskOrder($snapshot['order'],$userId,'Dodano etap produkcji');db()->commit();return $taskId;
 }
 $changes=[];$technical=false;
 $display=static function($key,$v):string{if($key==='parameters')return json_decode($v??'{}',true)['notes']??'brak';$table=['component_id'=>'item_components','operation_type_id'=>'operation_types','work_center_id'=>'work_centers'][$key]??null;return $table&&$v?reference($table,(int)$v)['name'].' (#'.$v.')':(planValue($v)?:'brak');};
 $names=['component_id'=>'część','operation_type_id'=>'operacja','work_center_id'=>'stanowisko','sequence_number'=>'kolejność','planned_quantity'=>'ilość','quantity_unit'=>'jednostka','planned_start_at'=>'planowany początek UTC','due_at'=>'termin UTC','instructions'=>'instrukcje','parameters'=>'parametry'];
 foreach($names as $key=>$name)if(planValue($old[$key])!==planValue($fields[$key])){
  $changes[]=$name.': '.$display($key,$old[$key]).' → '.$display($key,$fields[$key]);
  if(in_array($key,['component_id','operation_type_id','quantity_unit','parameters'],true))$technical=true;
 }
 $listDisplay=static function(string $key,array $values):string{
  $names=[];foreach($values as $value){
   if($key==='assignee_ids')$label=reference('users',$value)['display_name'];
   elseif($key==='depends_on')$label=reference('production_tasks',$value)['operation_name'];
   else{$scope=$key==='item_feature_ids'?'item':'component';$f=reference($scope.'_features',$value);$label=reference('feature_types',(int)$f['feature_type_id'])['name'];}
   $names[]=$label.' (#'.$value.')';
  }return $names?implode(', ',$names):'brak';
 };
 foreach($ids as $key=>$values)if($values!==$old[$key]){
  $changes[]=['assignee_ids'=>'Wykonawcy','depends_on'=>'Poprzednicy','item_feature_ids'=>'Cechy produktu','component_feature_ids'=>'Cechy części'][$key].': '.$listDisplay($key,$old[$key]).' → '.$listDisplay($key,$values);
  if(in_array($key,['item_feature_ids','component_feature_ids'],true))$technical=true;
 }
 $good=0;foreach($snapshot['reports'] as $r)if((int)$r['task_id']===$taskId&&(int)$r['plan_revision']===(int)$old['plan_revision'])$good+=(int)$r['good_quantity'];
 $waiting=false;foreach($ids['depends_on'] as $p)if($byId[$p]['status']!=='done')$waiting=true;
 $block=$technical||($old['status']==='done'&&$fields['planned_quantity']>$good)||($waiting&&in_array($old['status'],['done','in_progress'],true))||((int)$old['work_center_id']!==(int)$fields['work_center_id']&&$old['status']==='in_progress');
 $affected=[];if($block){
  $affected[$taskId]=true;
  do{$again=false;foreach($snapshot['task_dependencies'] as $d)if(isset($affected[$d['depends_on_task_id']])&&!isset($affected[$d['task_id']])){$affected[$d['task_id']]=true;$again=true;}}while($again);
 }
 $taskChanges=[];
 if($changes)$taskChanges[$taskId]=['id'=>$taskId,'before'=>$old,'after'=>$fields,'reason'=>'Edycja etapu: '.implode('; ',$changes)];
 foreach(array_keys($affected) as $id){$t=$byId[$id]??null;if(!$t||$t['status']==='cancelled')continue;
  $after=$taskChanges[$id]['after']??[];$after['status']='blocked';$after['finished_at']=null;
  if($technical){$after['plan_revision']=(int)$t['plan_revision']+1;$after['started_at']=null;}
  $reason=($id===$taskId?'Weryfikacja zmienionego etapu':'Weryfikacja po zmianie poprzednika #'.$taskId).($technical?'; nowa rewizja '.((int)$t['plan_revision']+1).', wykonanie od 0; wcześniejsze raporty zachowane':'; dotychczasowe wykonanie zachowane');
  $changes[]='Etap #'.$id.' '.$t['operation_name'].': wstrzymanie; '.$reason;
  $taskChanges[$id]=['id'=>$id,'before'=>$t,'after'=>$after,'reason'=>($taskChanges[$id]['reason']??'').'; '.$reason];
 }
 if($block){$orderStatus=reference('order_statuses',(int)$snapshot['order']['status_id']);if($orderStatus['is_terminal']||$orderStatus['code']==='ready')$changes[]='Zamówienie wróci do produkcji; data zakończenia zostanie usunięta.';}
 $plan=['changes'=>$changes,'tasks'=>array_values($taskChanges)];$key='task:'.$taskId;
 if($preview){db()->rollBack();$token=planApproval($key,$data,$snapshot,$plan,true);return ['changes'=>$changes,'confirmation_token'=>$token];}
 checkPlanToken($key,$data);planApproval($key,$data,$snapshot,$plan,false);
 // Zapis i dzienniki wszystkich dotkniętych etapów stanowią jedną transakcję.
 foreach($plan['tasks'] as $change){$f=$change['after'];$f['version']=(int)$change['before']['version']+1;updateRecord('production_tasks',$change['id'],$f);
  insert('task_status_history',['task_id'=>$change['id'],'old_status'=>$change['before']['status'],'new_status'=>$f['status']??$change['before']['status'],'event_type'=>'plan','changed_by'=>$userId,'reason'=>preg_replace('/^(.{0,10000}).*$/us','$1',$change['reason'])]);
 }
 writeTaskLinks($taskId,$ids,$userId);
 $accepted=$data;unset($accepted['confirmation_token']);
 insert('task_plan_changes',['task_id'=>$taskId,'changed_by'=>$userId,'changes'=>json_encode($changes,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'previous_snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'accepted_plan'=>json_encode($accepted,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
 if($block)reopenTaskOrder($snapshot['order'],$userId,'Wznowienie produkcji po edycji etapu');
 unset($_SESSION['production_approvals'][$key]);db()->commit();return $taskId;
}
function writeTaskLinks(int $id,array $ids,int $userId): void {
 foreach(['task_assignments'=>'assignee_ids','task_dependencies'=>'depends_on','task_item_features'=>'item_feature_ids','task_component_features'=>'component_feature_ids'] as $table=>$key){
  $column=['task_assignments'=>'user_id','task_dependencies'=>'depends_on_task_id','task_item_features'=>'item_feature_id','task_component_features'=>'component_feature_id'][$table];
  // Zachowaj oryginalną datę i autora niezmienionych przydziałów.
  $old=rows("SELECT $column FROM $table WHERE task_id=?",[$id]);$existing=array_map('intval',array_column($old,$column));
  foreach($existing as $value)if(!in_array($value,$ids[$key],true))run("DELETE FROM $table WHERE task_id=? AND $column=?",[$id,$value]);
  foreach($ids[$key] as $value)if(!in_array($value,$existing,true))insert($table,['task_id'=>$id,$column=>$value,...($table==='task_assignments'?['assigned_by'=>$userId]:[])]);
 }
}
// Pierwsza migawka zawierająca dany raport zachowuje nazwę operacji i jednostkę
// sprzed edycji. Późniejsze zmiany etapu nie przemianowują wykonanej pracy.
function productionReportsForOrder(int $id): array {
 $reports=rows('SELECT r.*,t.operation_name,t.quantity_unit,u.display_name FROM production_reports r JOIN production_tasks t ON t.id=r.task_id JOIN order_items i ON i.id=t.order_item_id JOIN users u ON u.id=r.reported_by WHERE i.order_id=? ORDER BY r.id DESC',[$id]);
 $positions=[];foreach($reports as $n=>$r)$positions[(int)$r['id']]=$n;$seen=[];
 foreach(rows('SELECT h.previous_snapshot FROM task_plan_changes h JOIN production_tasks t ON t.id=h.task_id JOIN order_items i ON i.id=t.order_item_id WHERE i.order_id=? ORDER BY h.id',[$id]) as $audit){
  $s=json_decode($audit['previous_snapshot'],true,512,JSON_THROW_ON_ERROR);$tasks=array_column($s['tasks'],null,'id');
  foreach($s['reports'] as $r){$rid=(int)$r['id'];if(isset($positions[$rid])&&!isset($seen[$rid])){
   $t=$tasks[$r['task_id']];$n=$positions[$rid];$reports[$n]['operation_name']=$t['operation_name'];$reports[$n]['quantity_unit']=$t['quantity_unit'];$seen[$rid]=true;
  }}
 }
 return $reports;
}
