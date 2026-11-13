<?php
declare(strict_types=1);
require_once __DIR__.'/production-state.php';

// Migawka obejmuje również raporty i zależności: ich zmiana unieważnia potwierdzenie.
function orderPlanSnapshot(int $id): array {
 $s=['order'=>reference('orders',$id)];
 $s['items']=rows('SELECT * FROM order_items WHERE order_id=? ORDER BY id',[$id]);
 $s['components']=rows('SELECT c.* FROM item_components c JOIN order_items i ON i.id=c.order_item_id WHERE i.order_id=? ORDER BY c.id',[$id]);
 $s['inks']=rows('SELECT k.* FROM component_inks k JOIN item_components c ON c.id=k.component_id JOIN order_items i ON i.id=c.order_item_id WHERE i.order_id=? ORDER BY k.id',[$id]);
 foreach(['item','component'] as $scope){
  $join=$scope==='item'?'JOIN order_items i ON i.id=f.order_item_id':'JOIN item_components c ON c.id=f.component_id JOIN order_items i ON i.id=c.order_item_id';
  $s[$scope.'_features']=rows("SELECT f.*,ft.name feature_name FROM {$scope}_features f $join JOIN feature_types ft ON ft.id=f.feature_type_id WHERE i.order_id=? ORDER BY f.id",[$id]);
  $s[$scope.'_values']=rows("SELECT v.*,d.code FROM {$scope}_feature_values v JOIN {$scope}_features f ON f.id=v.{$scope}_feature_id $join JOIN feature_parameter_definitions d ON d.id=v.parameter_definition_id WHERE i.order_id=? ORDER BY f.id,d.id",[$id]);
 }
 $s['tasks']=rows('SELECT t.* FROM production_tasks t JOIN order_items i ON i.id=t.order_item_id WHERE i.order_id=? ORDER BY t.id FOR UPDATE',[$id]);
 $s['reports']=rows('SELECT r.* FROM production_reports r JOIN production_tasks t ON t.id=r.task_id JOIN order_items i ON i.id=t.order_item_id WHERE i.order_id=? ORDER BY r.id',[$id]);
 foreach(['task_dependencies','task_item_features','task_component_features'] as $table)$s[$table]=rows("SELECT l.* FROM $table l JOIN production_tasks t ON t.id=l.task_id JOIN order_items i ON i.id=t.order_item_id WHERE i.order_id=? ORDER BY l.task_id,".($table==='task_dependencies'?'l.depends_on_task_id':($table==='task_item_features'?'l.item_feature_id':'l.component_feature_id')),[$id]);
 return $s;
}
function planValue(mixed $v): string { return $v===null?'':(is_numeric($v)?(string)(float)$v:trim((string)$v)); }
function buildOrderPlan(array $s,array $data): array {
 $changes=[];$reasons=[];$revisions=[];$cancel=[];$items=[];$components=[];
 $oldItems=array_column($s['items'],null,'id');$oldComponents=array_column($s['components'],null,'id');
 $addReason=static function(int $task,string $reason,bool $revision=false)use(&$reasons,&$revisions):void{$reasons[$task][]=$reason;if($revision)$revisions[$task]=true;};
 $affect=static function(int $item,?int $component,string $reason,bool $revision=false)use($s,$addReason):void{
  foreach($s['tasks'] as $t)if((int)$t['order_item_id']===$item&&($component===null||$t['component_id']===null||(int)$t['component_id']===$component))$addReason((int)$t['id'],$reason,$revision);
 };
 $fields=static function(array $old,array $new,array $labels,string $label)use(&$changes):array{
  $changed=[];
  foreach($labels as $key=>$name)if(array_key_exists($key,$new)&&planValue($old[$key]??null)!==planValue($new[$key])){
   $display=static function(mixed $v)use($key):string{
    $table=['paper_variant_id'=>'paper_variants','product_type_id'=>'product_types','customer_id'=>'customers','component_type_id'=>'component_types'][$key]??null;
    return $table&&$v?reference($table,(int)$v)['name'].' (#'.$v.')':(planValue($v)?:'brak');
   };
   $changes[]=$label.' — '.$name.': '.$display($old[$key]??null).' → '.$display($new[$key]);$changed[]=$key;
  }
  return $changed;
 };
 $featureChanges=static function(string $scope,int $owner,array $incoming)use($s,&$changes,&$cancel,$addReason):void{
  $key=$scope==='item'?'order_item_id':'component_id';$existing=array_filter($s[$scope.'_features'],fn($f)=>!(int)$f['is_archived']&&(int)$f[$key]===$owner);$submitted=[];
  foreach($incoming as $f)if(isset($f['id']))$submitted[(int)$f['id']]=$f;else $changes[]='Nowa cecha: '.reference('feature_types',integer($f,'feature_type_id'))['name'].' — dobierz zadanie i wykonawcę w planie produkcji.';
  foreach($existing as $f){
   $fid=(int)$f['id'];$new=$submitted[$fid]??null;$oldValues=[];
   foreach($s[$scope.'_values'] as $v)if((int)$v[$scope.'_feature_id']===$fid)$oldValues[$v['code']]=planValue($v['value_text']??$v['value_number']??$v['value_boolean']);
   $newValues=[];foreach(($new['values']??[]) as $key=>$v)if($v!==null&&$v!=='')$newValues[$key]=planValue($v);ksort($oldValues);ksort($newValues);
   if($new===null)$reason='Usunięcie cechy '.$f['feature_name'].' (#'.$fid.')';
   elseif((int)$new['feature_type_id']!==(int)$f['feature_type_id'])$reason='Zmiana cechy '.$f['feature_name'].' → '.reference('feature_types',integer($new,'feature_type_id'))['name'].' (#'.$fid.')';
   elseif($oldValues!==$newValues)$reason='Zmiana parametrów cechy '.$f['feature_name'].' (#'.$fid.'): '.json_encode($oldValues,JSON_UNESCAPED_UNICODE).' → '.json_encode($newValues,JSON_UNESCAPED_UNICODE);
   elseif(planValue($f['notes'])!==planValue($new['notes']??''))$reason='Zmiana uwag cechy '.$f['feature_name'].' (#'.$fid.')';
   else continue;
   $changes[]=$reason;
   foreach($s['task_'.$scope.'_features'] as $link)if((int)$link[$scope.'_feature_id']===$fid){
    if($new===null)$cancel[(int)$link['task_id']]=$reason;
    else $addReason((int)$link['task_id'],str_starts_with($reason,'Zmiana parametrów')?'Zmiana parametrów cechy '.$f['feature_name'].' (#'.$fid.')':$reason,!(str_starts_with($reason,'Zmiana uwag')));
   }
  }
 };
 foreach($data['items'] as $item){
  if(!isset($item['id'])){$changes[]='Dodanie produktu '.$item['name'].' — zaplanuj jego zadania i wykonawców w karcie zamówienia.';continue;}
  $iid=integer($item,'id');$items[$iid]=$item;$old=$oldItems[$iid]??fail('Produkt nie należy do zamówienia.');
  $changed=$fields($old,$item,['name'=>'nazwa','quantity'=>'nakład','product_type_id'=>'rodzaj produktu','finished_width_mm'=>'szerokość','finished_height_mm'=>'wysokość','specification_notes'=>'informacje','agreed_total_net'=>'wartość netto'],'Produkt '.$old['name']);
  if(array_intersect($changed,['product_type_id','finished_width_mm','finished_height_mm']))$affect($iid,null,'Zmiana specyfikacji produktu '.$old['name'],true);
  if(in_array('specification_notes',$changed,true))$affect($iid,null,'Zmiana informacji do produktu '.$old['name']);
  $featureChanges('item',$iid,$item['features']??[]);
  foreach(($item['components']??[]) as $c){
   if(!isset($c['id'])){$changes[]='Dodanie części '.$c['name'].' — zaplanuj potrzebne zadania i wykonawców.';continue;}
   $cid=integer($c,'id');$components[$cid]=$c;$oldC=$oldComponents[$cid]??fail('Część nie należy do zamówienia.');
   $changed=$fields($oldC,$c,['name'=>'nazwa','component_type_id'=>'rodzaj części','page_count'=>'liczba stron','paper_variant_id'=>'papier','print_technique'=>'technika druku','offset_front_colors'=>'kolory awersu','offset_back_colors'=>'kolory rewersu','digital_front_mode'=>'tryb awersu','digital_back_mode'=>'tryb rewersu','bleed_mm'=>'spad','notes'=>'informacje'],'Część '.$oldC['name']);
   $inks=[];foreach(['front','back'] as $side){$inks[$side]=array_values(array_map(fn($k)=>$k['ink_name'],array_filter($s['inks'],fn($k)=>(int)$k['component_id']===$cid&&$k['side']===$side)));}
   if($c['print_technique']==='offset'&&($inks['front']!==($c['front_inks']??[])||$inks['back']!==($c['back_inks']??[]))){$changed[]='inks';$changes[]='Część '.$oldC['name'].' — farby: '.json_encode($inks,JSON_UNESCAPED_UNICODE).' → '.json_encode(['front'=>$c['front_inks']??[],'back'=>$c['back_inks']??[]],JSON_UNESCAPED_UNICODE);}
   if(array_diff($changed,['name','notes','component_type_id']))$affect($iid,$cid,'Zmiana specyfikacji części '.$oldC['name'],true);
   if(in_array('notes',$changed,true))$affect($iid,$cid,'Zmiana informacji do części '.$oldC['name']);
   $featureChanges('component',$cid,$c['features']??[]);
  }
 }
 foreach($s['items'] as $old)if(!(int)$old['is_archived']&&!isset($items[$old['id']])){
  $changes[]='Usunięcie produktu '.$old['name'].' (archiwizacja z zachowaniem historii)';
  foreach($s['tasks'] as $t)if($t['order_item_id']===$old['id'])$cancel[(int)$t['id']]='Usunięcie produktu '.$old['name'];
 }
 foreach($s['components'] as $old)if(!(int)$old['is_archived']&&isset($items[$old['order_item_id']])&&!isset($components[$old['id']])){
  $changes[]='Usunięcie części '.$old['name'].' (archiwizacja z zachowaniem historii)';
  foreach($s['tasks'] as $t)if((int)$t['order_item_id']===(int)$old['order_item_id']&&($t['component_id']===null||$t['component_id']===$old['id']))$cancel[(int)$t['id']]='Usunięcie części '.$old['name'];
 }
 $oldDue=$s['order']['due_at'];$newDue=empty($data['due_at'])?null:str_replace('T',' ',$data['due_at']).':00';
 $fields($s['order'],$data,['title'=>'tytuł','customer_id'=>'klient','priority'=>'priorytet','contact_name'=>'kontakt','contact_email'=>'e-mail','contact_phone'=>'telefon','customer_notes'=>'informacje','internal_notes'=>'notatki'],'Zamówienie');
 if($oldDue!==$newDue)$changes[]='Termin zamówienia (UTC): '.($oldDue??'brak').' → '.($newDue??'brak');
 // Anulowanie poprzednika anuluje też niezakończone etapy zależne, zamiast zostawiać je w martwej kolejce.
 $byId=array_column($s['tasks'],null,'id');
 foreach(dependentTaskIds($s['task_dependencies'],array_keys($cancel)) as $tid)if(!isset($cancel[$tid]))$cancel[$tid]='Anulowanie wcześniejszego etapu';
 // Zmiana technologii poprzednika wymaga również weryfikacji dalszych etapów.
 do{$again=false;foreach($s['task_dependencies'] as $d)if(isset($revisions[$d['depends_on_task_id']])&&!isset($revisions[$d['task_id']])){$addReason((int)$d['task_id'],'Zmiana specyfikacji poprzednika #'.$d['depends_on_task_id'],true);$again=true;}}while($again);
 $quantityReopened=[];
 foreach($s['tasks'] as $t){
  $item=$items[$t['order_item_id']]??null;$component=$t['component_id']?($oldComponents[$t['component_id']]??null):null;
  if(!$item||isset($cancel[$t['id']])||$t['status']!=='done'||($component&&(int)$component['is_archived'])||(int)$item['quantity']===(int)$oldItems[$t['order_item_id']]['quantity'])continue;
  $quantity=taskQuantityForCopies($t,integer($item,'quantity'));$good=0;
  foreach($s['reports'] as $r)if((int)$r['task_id']===(int)$t['id']&&(int)$r['plan_revision']===(int)$t['plan_revision'])$good+=(int)$r['good_quantity'];
  if($quantity>$good)$quantityReopened[]=(int)$t['id'];
 }
 $quantityReview=array_fill_keys(dependentTaskIds($s['task_dependencies'],$quantityReopened),true);
 $tasks=[];
 foreach($s['tasks'] as $t){
  $tid=(int)$t['id'];$after=[];$messages=[];
  if($t['status']==='cancelled'||($t['component_id']&&isset($oldComponents[$t['component_id']])&&(int)$oldComponents[$t['component_id']]['is_archived']))continue;
  if(isset($cancel[$tid])){
   if($t['status']==='done'){$changes[]='Zadanie #'.$tid.' '.$t['operation_name'].' już zakończono — zostaje w historii; '.$cancel[$tid].'.';continue;}
   $after=['status'=>'cancelled'];$messages[]=$cancel[$tid].'; status: '.$t['status'].' → anulowane';
  }else{
   $item=$items[$t['order_item_id']]??null;
   if($item&&(int)$item['quantity']!==(int)$oldItems[$t['order_item_id']]['quantity']){
    $quantity=taskQuantityForCopies($t,integer($item,'quantity'));
    if($quantity>100000000)fail('Przeliczony plan zadania przekracza dopuszczalną ilość.');
    if($quantity!==(int)$t['planned_quantity']){$after['planned_quantity']=$quantity;$messages[]='ilość: '.$t['planned_quantity'].' → '.$quantity.' '.$t['quantity_unit'].' (z zapisanej podstawy, zaokrąglenie w górę)';}
   }
   $good=0;foreach($s['reports'] as $r)if((int)$r['task_id']===$tid&&(int)$r['plan_revision']===(int)$t['plan_revision'])$good+=(int)$r['good_quantity'];
   if($oldDue!==$newDue&&($t['status']!=='done'||isset($revisions[$tid])||(isset($after['planned_quantity'])&&$after['planned_quantity']>$good))){
    $due=$newDue===null?null:($oldDue!==null&&$t['due_at']!==null?gmdate('Y-m-d H:i:s',strtotime($t['due_at'])+strtotime($newDue)-strtotime($oldDue)):$newDue);
    if($oldDue!==null&&$newDue!==null&&$t['planned_start_at']!==null){$after['planned_start_at']=gmdate('Y-m-d H:i:s',strtotime($t['planned_start_at'])+strtotime($newDue)-strtotime($oldDue));$messages[]='planowany start (UTC): '.$t['planned_start_at'].' → '.$after['planned_start_at'];}
    if($due!==$t['due_at']){$after['due_at']=$due;$messages[]='termin (UTC): '.($t['due_at']??'brak').' → '.($due??'brak');}
   }
   if(isset($revisions[$tid])){
    $after['plan_revision']=(int)$t['plan_revision']+1;$after['status']='blocked';$after['started_at']=null;$after['finished_at']=null;
    $messages[]='nowa rewizja planu '.$after['plan_revision'].'; wstrzymanie do weryfikacji. Dotychczasowe raporty pozostają w historii; wykonanie nowej specyfikacji liczone od 0';
   }elseif(isset($quantityReview[$tid])){
    $after['status']='blocked';$after['finished_at']=null;$messages[]='weryfikacja po ilościowym wznowieniu etapu lub poprzednika; dotychczasowe wykonanie '.$good.' pozostaje zachowane';
   }
   foreach(array_unique($reasons[$tid]??[]) as $reason)$messages[]=$reason;
  }
  if($after||$messages){$reason=implode('; ',$messages);$tasks[]=['id'=>$tid,'operation'=>$t['operation_name'],'before'=>$t,'after'=>$after,'reason'=>$reason];$changes[]='Zadanie #'.$tid.' '.$t['operation_name'].' / '.$oldItems[$t['order_item_id']]['name'].': '.$reason;}
 }
 $reopen=false;foreach($tasks as $t)if(isset($t['after']['status'])&&$t['after']['status']==='blocked')$reopen=true;
 $status=reference('order_statuses',(int)$s['order']['status_id']);$orderStatus=null;
 if($reopen&&($status['is_terminal']||$status['code']==='ready')){$orderStatus=(int)(one("SELECT id FROM order_statuses WHERE code='production'")['id']??fail('Brak systemowego statusu produkcji.'));$changes[]='Status zamówienia: '.$status['name'].' → W produkcji; usunięcie daty zakończenia.';}
 return ['changes'=>$changes,'tasks'=>$tasks,'order_status_id'=>$orderStatus];
}
function applyOrderPlan(array $plan,int $userId): void {
 foreach($plan['tasks'] as $change){
  $fields=$change['after'];$fields['version']=(int)$change['before']['version']+1;
  updateRecord('production_tasks',$change['id'],$fields);
  insert('task_status_history',['task_id'=>$change['id'],'old_status'=>$change['before']['status'],'new_status'=>$fields['status']??$change['before']['status'],'event_type'=>'plan','changed_by'=>$userId,'reason'=>$change['reason']]);
 }
}
