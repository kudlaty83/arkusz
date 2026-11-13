<?php
declare(strict_types=1);
function updateRecord(string $table,int $id,array $fields): void {
 $assign=implode(',',array_map(fn($key)=>"`$key`=?",array_keys($fields)));
 run("UPDATE $table SET $assign WHERE id=?",[...array_values($fields),$id]);
}

function retainedReference(string $table,int $id,mixed $oldId): array {
 return $oldId!==null && (int)$oldId===$id?reference($table,$id):activeReference($table,$id);
}

function syncFeatures(int $item,?int $component,array $features): void {
 if(count($features)>100)fail('Zbyt wiele cech.');
 $prefix=$component?'component':'item';$owner=$component?'component_id':'order_item_id';
 $old=rows("SELECT id FROM {$prefix}_features WHERE $owner=? AND is_archived=0",[$component??$item]);$seen=[];
 foreach($features as $f){
  if(isset($f['id'])&&in_array((int)$f['id'],$seen,true))fail('Powielona cecha.');
  $seen[]=feature($item,$component,$f);
 }
 foreach($old as $f)if(!in_array((int)$f['id'],$seen,true)){
  run("UPDATE {$prefix}_features SET is_archived=1 WHERE id=?",[$f['id']]);
 }
}

function saveOrder(array $data,bool $editing,int $userId,bool $preview=false): int|array {
  permit('orders.edit');
  if(empty($data['items'])||!is_array($data['items'])||count($data['items'])>50) fail('Dodaj od 1 do 50 produktów.');
  $customerId=integer($data,'customer_id'); $title=text($data,'title');
  $due=text($data,'due_at',16,false); if($due!=='' && (!DateTime::createFromFormat('!Y-m-d\TH:i',$due) || DateTime::createFromFormat('!Y-m-d\TH:i',$due)->format('Y-m-d\TH:i')!==$due)) fail('Nieprawidłowy termin.');
  $priority=$data['priority']??'normal';if(!in_array($priority,['low','normal','high','urgent'],true)) fail('Nieprawidłowy priorytet.');
  db()->beginTransaction();
  $customer=one('SELECT * FROM customers WHERE id=? FOR UPDATE',[$customerId])??fail('Brak klienta.',404);
  $previous=null;
  if($editing){
   $id=integer($data,'id');
   $previous=one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id])??fail('Brak zamówienia.',404);
   if((int)$previous['version']!==integer($data,'version'))fail('Zamówienie zmieniła inna osoba. Odśwież widok i ponów edycję.',409);
   $snapshot=orderPlanSnapshot($id);$plan=buildOrderPlan($snapshot,$data);
   if($plan['tasks'])permit('tasks.manage');
   $specification=$data;unset($specification['confirmation_token']);
   $fingerprint=hash('sha256',json_encode([$specification,$snapshot,$plan,$customer],JSON_THROW_ON_ERROR));
   if(!$preview){
    $approval=$_SESSION['order_plan_approval'][$id]??null;
    if(!$approval||!hash_equals($approval['token'],(string)($data['confirmation_token']??''))||!hash_equals($approval['fingerprint'],$fingerprint)||$approval['expires']<time())fail('Plan lub formularz zmienił się od podglądu. Sprawdź ponownie podsumowanie i zatwierdź zmiany.',409);
   }

  }

  if(!(int)$customer['is_active']&&(!$previous||(int)$previous['customer_id']!==(int)$customer['id']))fail('Wybierz aktywnego klienta.');

  $status=one("SELECT id FROM order_statuses WHERE code='new'")['id'];
  $header=['order_number'=>'TMP-'.bin2hex(random_bytes(12)),'customer_id'=>$customer['id'],'customer_name'=>$customer['name'],'customer_tax_id'=>$customer['tax_id'],'customer_address'=>$customer['address_text'],'contact_name'=>text($data,'contact_name',200,false),'contact_email'=>text($data,'contact_email',254,false)?:($editing?'':$customer['email']),'contact_phone'=>text($data,'contact_phone',50,false)?:($editing?'':$customer['phone']),'title'=>$title,'status_id'=>$status,'priority'=>$priority,'due_at'=>$due?str_replace('T',' ',$due).':00':null,'internal_notes'=>text($data,'internal_notes',10000,false),'created_by'=>$userId,'customer_notes'=>text($data,'customer_notes',10000,false)];
  if($editing){
   unset($header['order_number'],$header['status_id'],$header['created_by']);
   if((int)$previous['customer_id']===(int)$customer['id'])unset($header['customer_name'],$header['customer_tax_id'],$header['customer_address']);
   updateRecord('orders',$id,$header);run('UPDATE orders SET version=version+1 WHERE id=?',[$id]);
  }else $id=insert('orders',$header);
  if(!$editing)run('UPDATE orders SET order_number=? WHERE id=?',['Z/'.date('Y').'/'.str_pad((string)$id,5,'0',STR_PAD_LEFT),$id]);
  insert('order_status_history',['order_id'=>$id,'old_status_id'=>$previous['status_id']??null,'new_status_id'=>$previous['status_id']??$status,'changed_by'=>$userId,'reason'=>$editing?'Edycja danych i specyfikacji zamówienia':'Przyjęcie zamówienia']);
  $existingItems=$editing?rows('SELECT * FROM order_items WHERE order_id=? AND is_archived=0 ORDER BY line_number',[$id]):[];
  $seenItems=[];$nextLine=$editing?(int)one('SELECT COALESCE(MAX(line_number),0) n FROM order_items WHERE order_id=?',[$id])['n']:0;
  foreach($data['items'] as $idx=>$item) {
   $oldItem=null;
   if(isset($item['id'])){
    if(!$editing)fail('Nowe zamówienie nie może zawierać istniejących produktów.');
    $oldItem=reference('order_items',integer($item,'id'));
    if((int)$oldItem['is_archived']||(int)$oldItem['order_id']!==$id||in_array((int)$oldItem['id'],$seenItems,true))fail('Nieprawidłowe powiązanie produktu.');
    $seenItems[]=(int)$oldItem['id'];
   }

   $type=retainedReference('product_types',integer($item,'product_type_id'),$oldItem['product_type_id']??null);
   $itemFields=['order_id'=>$id,'line_number'=>$oldItem['line_number']??++$nextLine,'product_type_id'=>$type['id'],'product_type_name'=>$oldItem&&(int)$oldItem['product_type_id']===(int)$type['id']?$oldItem['product_type_name']:$type['name'],'name'=>text($item,'name'),'quantity'=>integer($item,'quantity'),'finished_width_mm'=>num($item,'finished_width_mm',1,999999),'finished_height_mm'=>num($item,'finished_height_mm',1,999999),'agreed_total_net'=>isset($item['agreed_total_net'])&&$item['agreed_total_net']!==''?num($item,'agreed_total_net',0,999999999999):null,'tax_rate'=>$oldItem['tax_rate']??23,'specification_notes'=>text($item,'specification_notes',10000,false)];
   if($oldItem){$itemId=(int)$oldItem['id'];updateRecord('order_items',$itemId,$itemFields);}else $itemId=insert('order_items',$itemFields);
   syncFeatures($itemId,null,$item['features']??[]);
   if(empty($item['components'])||count($item['components'])>50) fail('Produkt wymaga od 1 do 50 części.');
   $oldComponents=$oldItem?rows('SELECT id FROM item_components WHERE order_item_id=? AND is_archived=0',[$itemId]):[];
   $seenComponents=[];
   foreach($item['components'] as $ci=>$c){
    $oldComponent=null;
    if(isset($c['id'])){
     if(!$oldItem)fail('Nowy produkt nie może zawierać istniejących części.');
     $oldComponent=reference('item_components',integer($c,'id'));
     if((int)$oldComponent['is_archived']||(int)$oldComponent['order_item_id']!==$itemId||in_array((int)$oldComponent['id'],$seenComponents,true))fail('Nieprawidłowe powiązanie części.');
     $seenComponents[]=(int)$oldComponent['id'];
    }

    $paper=retainedReference('paper_variants',integer($c,'paper_variant_id'),$oldComponent['paper_variant_id']??null);if(!$oldComponent||(int)$oldComponent['paper_variant_id']!==(int)$paper['id'])activeReference('paper_types',(int)$paper['paper_type_id']); $tech=$c['print_technique']??''; if(!in_array($tech,['offset','digital'],true)) fail('Nieprawidłowa technika druku.');
    $fields=['order_item_id'=>$itemId,'name'=>text($c,'name',150),'sort_order'=>$ci,'page_count'=>integer($c,'page_count'),'paper_variant_id'=>$paper['id'],'paper_description'=>$oldComponent&&(int)$oldComponent['paper_variant_id']===(int)$paper['id']?$oldComponent['paper_description']:$paper['name'],'grammage_gsm'=>$oldComponent&&(int)$oldComponent['paper_variant_id']===(int)$paper['id']?$oldComponent['grammage_gsm']:$paper['grammage_gsm'],'print_technique'=>$tech,'notes'=>text($c,'notes',10000,false),'bleed_mm'=>isset($c['bleed_mm'])?num($c,'bleed_mm',0,999.99):($oldComponent['bleed_mm']??3),'offset_front_colors'=>null,'offset_back_colors'=>null,'digital_front_mode'=>null,'digital_back_mode'=>null];
    if($tech==='offset') { $front=integer($c,'offset_front_colors',0,5);$back=integer($c,'offset_back_colors',0,5);if($front+$back===0)fail('Wybierz co najmniej jeden kolor.');$fields['offset_front_colors']=$front;$fields['offset_back_colors']=$back; }
    else { foreach(['digital_front_mode','digital_back_mode'] as $key) { if(!in_array($c[$key]??'',['none','bw','full_color'],true)) fail('Nieprawidłowy tryb cyfry.');$fields[$key]=$c[$key]; } if($fields['digital_front_mode']==='none'&&$fields['digital_back_mode']==='none')fail('Wybierz stronę do druku.'); }
    if($oldComponent){$cid=(int)$oldComponent['id'];updateRecord('item_components',$cid,$fields);run('DELETE FROM component_inks WHERE component_id=?',[$cid]);}else $cid=insert('item_components',$fields);
    if($tech==='offset') foreach(['front'=>$front,'back'=>$back] as $side=>$count){ $names=$c[$side.'_inks']??[]; if(count($names)!==$count) fail('Podaj nazwy wszystkich farb.'); foreach($names as $n=>$ink) insert('component_inks',['component_id'=>$cid,'side'=>$side,'ink_number'=>$n+1,'ink_name'=>text(['ink'=>$ink],'ink',100)]); }
    syncFeatures($itemId,$cid,$c['features']??[]);
   }
   foreach($oldComponents as $old)if(!in_array((int)$old['id'],$seenComponents,true))run('UPDATE item_components SET is_archived=1 WHERE id=?',[$old['id']]);
  }
  foreach($existingItems as $old)if(!in_array((int)$old['id'],$seenItems,true))run('UPDATE order_items SET is_archived=1 WHERE id=?',[$old['id']]);
  if($editing){
   applyOrderPlan($plan,$userId);
   if($plan['order_status_id']!==null){
    run('UPDATE orders SET status_id=?,completed_at=NULL WHERE id=?',[$plan['order_status_id'],$id]);
    insert('order_status_history',['order_id'=>$id,'old_status_id'=>$previous['status_id'],'new_status_id'=>$plan['order_status_id'],'changed_by'=>$userId,'reason'=>'Ponowne otwarcie produkcji po zatwierdzonej zmianie planu']);
   }
   if($preview){
    db()->rollBack();$token=bin2hex(random_bytes(32));
    $_SESSION['order_plan_approval'][$id]=['token'=>$token,'fingerprint'=>$fingerprint,'expires'=>time()+900];
    return ['changes'=>$plan['changes'],'confirmation_token'=>$token,'task_count'=>count($plan['tasks'])];
   }
   insert('order_change_history',['order_id'=>$id,'changed_by'=>$userId,'changes'=>json_encode($plan['changes'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'previous_snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'accepted_specification'=>json_encode($specification,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
   unset($_SESSION['order_plan_approval'][$id]);
  }
  db()->commit();return $id;
}
