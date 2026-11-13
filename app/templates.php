<?php
declare(strict_types=1);
function templateDetails(int $id,bool $lock=false): array {
 $template=one('SELECT * FROM production_templates WHERE id=?'.($lock?' FOR UPDATE':''),[$id])??fail('Brak szablonu.',404);
 $template['steps']=rows('SELECT * FROM production_template_steps WHERE template_id=? ORDER BY step_number',[$id]);return $template;
}
function templatePage(array $input): array {
 permit('tasks.manage');[$page,$size]=listPage($input);$query=text($input,'q',120,false);$where=[];$args=[];
 $active=$input['active']??'1';if(!in_array((string)$active,['0','1','all'],true))fail('Nieprawidłowy filtr szablonów.');
 if($active!=='all'){$where[]='t.is_active=?';$args[]=(int)$active;}
 if($query!==''){$where[]="t.name LIKE ? ESCAPE '!'";$args[]=prefixSearch($query);}
 if(!empty($input['component_id'])){
  $c=reference('item_components',integer($input,'component_id'));$item=reference('order_items',(int)$c['order_item_id']);
  $where[]='t.component_type_id=? AND (t.product_type_id IS NULL OR t.product_type_id=?) AND (t.print_technique IS NULL OR t.print_technique=?)';
  $args=[...$args,$c['component_type_id'],$item['product_type_id'],$c['print_technique']];
 }
 $filter=$where?' WHERE '.implode(' AND ',$where):'';db()->beginTransaction();
 $total=(int)one('SELECT COUNT(*) n FROM production_templates t'.$filter,$args)['n'];$pages=max(1,(int)ceil($total/$size));$page=min($page,$pages);$offset=($page-1)*$size;
 $entries=rows("SELECT t.*,c.name component_type_name,(SELECT COUNT(*) FROM production_template_steps s WHERE s.template_id=t.id) step_count FROM production_templates t JOIN component_types c ON c.id=t.component_type_id$filter ORDER BY t.name,t.id LIMIT $size OFFSET $offset",$args);
 db()->commit();return ['entries'=>$entries,'total'=>$total,'page'=>$page,'pages'=>$pages,'page_size'=>$size];
}
function saveTemplate(array $data,int $userId): int {
 permit('tasks.manage');$id=isset($data['id'])?integer($data,'id'):null;
 if(!is_array($data['steps']??null)||!array_is_list($data['steps'])||count($data['steps'])<1||count($data['steps'])>100)fail('Szablon wymaga od 1 do 100 etapów.');
 db()->beginTransaction();$old=$id?templateDetails($id,true):null;
 if($old&&(int)$old['version']!==integer($data,'version'))fail('Szablon zmieniła inna osoba. Otwórz go ponownie.',409);
 $kind=retainedReference('component_types',integer($data,'component_type_id'),$old['component_type_id']??null);
 $product=!empty($data['product_type_id'])?retainedReference('product_types',integer($data,'product_type_id'),$old['product_type_id']??null):null;
 $tech=$data['print_technique']??'';if(!in_array($tech,['','offset','digital'],true))fail('Nieprawidłowa technika druku.');
 $active=$data['is_active']??1;if(!in_array($active,[0,1,'0','1',true,false],true))fail('Nieprawidłowy status szablonu.');
 $fields=['name'=>text($data,'name',200),'component_type_id'=>$kind['id'],'product_type_id'=>$product['id']??null,'print_technique'=>$tech?:null,'description'=>text($data,'description',10000,false),'is_active'=>(int)$active];
 if($old){$fields['version']=(int)$old['version']+1;updateRecord('production_templates',$id,$fields);run('DELETE FROM production_template_steps WHERE template_id=?',[$id]);}
 else $id=insert('production_templates',[...$fields,'created_by'=>$userId]);
 foreach($data['steps'] as $n=>$step){
  if(!is_array($step))fail('Nieprawidłowa definicja etapu szablonu.');
  $prior=$old['steps'][$n]??null;
  $op=retainedReference('operation_types',integer($step,'operation_type_id'),$prior['operation_type_id']??null);$center=retainedReference('work_centers',integer($step,'work_center_id'),$prior['work_center_id']??null);
  $mode=$step['quantity_mode']??'per_copy';if(!in_array($mode,['per_copy','fixed'],true))fail('Nieprawidłowy sposób liczenia ilości.');
  $value=num($step,'quantity_value',0.0001,99999999.9999);if(round($value,4)!=$value||($mode==='fixed'&&floor($value)!=$value))fail('Współczynnik ma najwyżej 4 miejsca po przecinku, a stała ilość jest całkowita.');
  $depends=$step['depends_on_previous']??1;if(!in_array($depends,[0,1,'0','1',false,true],true))fail('Niepoprawna zależność etapów.');
  insert('production_template_steps',['template_id'=>$id,'step_number'=>$n+1,'operation_type_id'=>$op['id'],'work_center_id'=>$center['id'],'quantity_mode'=>$mode,'quantity_value'=>$value,'quantity_unit'=>text($step,'quantity_unit',30),'days_before_due'=>integer($step,'days_before_due',0,3650),'depends_on_previous'=>$n>0?(int)$depends:0,'instructions'=>text($step,'instructions',10000,false),'parameter_notes'=>text($step,'parameter_notes',10000,false)]);
 }
 db()->commit();return $id;
}
function applyTemplate(array $data,int $userId,bool $preview): array {
 permit('tasks.manage');db()->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');db()->beginTransaction();$c=reference('item_components',integer($data,'component_id'));
 [$item,$snapshot]=lockTaskOrder((int)$c['order_item_id']);$c=reference('item_components',(int)$c['id']);
 if((int)$c['is_archived'])fail('Część została wycofana.');
 $template=templateDetails(integer($data,'template_id'),true);
 if(!(int)$template['is_active'])fail('Szablon jest nieaktywny.');activeReference('component_types',(int)$template['component_type_id']);
 if((int)$c['component_type_id']!==(int)$template['component_type_id']||($template['product_type_id']!==null&&(int)$template['product_type_id']!==(int)$item['product_type_id'])||($template['print_technique']!==null&&$template['print_technique']!==$c['print_technique']))fail('Szablon nie pasuje do rodzaju części, produktu lub techniki druku.');
 if(one('SELECT id FROM component_template_applications WHERE component_id=? AND template_id=?',[$c['id'],$template['id']]))fail('Ten szablon został już zastosowany do tej części. Edytuj istniejące etapy.',409);
 $last=0;foreach($snapshot['tasks'] as $t)if((int)$t['order_item_id']===(int)$item['id'])$last=max($last,(int)$t['sequence_number']);
 $parent=!empty($data['after_task_id'])?integer($data,'after_task_id'):null;
 if($parent){$p=reference('production_tasks',$parent);if((int)$p['order_item_id']!==(int)$item['id']||$p['status']==='cancelled')fail('Poprzednik musi być nieanulowanym etapem tego produktu.');}
 $assignees=$data['assignees']??[];if(!is_array($assignees))fail('Nieprawidłowe przydziały.');
 $plan=[];$changes=['Dodanie etapów szablonu „'.$template['name'].'”, wersja '.$template['version'].', do części „'.$c['name'].'”. Istniejące etapy i raporty pozostaną zachowane.'];
 foreach($template['steps'] as $n=>$step){
  $op=activeReference('operation_types',(int)$step['operation_type_id']);$center=activeReference('work_centers',(int)$step['work_center_id']);
  $rule=templateQuantityRule($step);$quantity=taskQuantityForCopies($rule,(int)$item['quantity']);if($quantity<1||$quantity>100000000)fail('Ilość etapu szablonu wykracza poza dozwolony zakres.');
  $sequence=$last+10*($n+1);if($sequence>65535)fail('Brak miejsca w numeracji etapów. Zmień kolejność istniejących etapów.');
  $due=$snapshot['order']['due_at']?gmdate('Y-m-d H:i:s',strtotime($snapshot['order']['due_at'])-(int)$step['days_before_due']*86400):null;
  $workers=userIds(['assignee_ids'=>$assignees[$step['step_number']]??[]],'assignee_ids');sort($workers);taskAssignees($workers,(int)$center['id']);
  $plan[]=[...$rule,'operation_type_id'=>$op['id'],'operation_name'=>$op['name'],'work_center_id'=>$center['id'],'planned_quantity'=>$quantity,'quantity_unit'=>$step['quantity_unit'],'sequence_number'=>$sequence,'due_at'=>$due,'instructions'=>$step['instructions'],'parameters'=>$step['parameter_notes']!==''?json_encode(['notes'=>$step['parameter_notes']],JSON_UNESCAPED_UNICODE):null,'assignee_ids'=>$workers,'depends_on_previous'=>(int)$step['depends_on_previous'],'step_number'=>(int)$step['step_number']];
  $workerNames=[];foreach($workers as $uid)$workerNames[]=reference('users',$uid)['display_name'];
  $changes[]='Etap '.($n+1).': '.$op['name'].' / '.$center['name'].' — '.$quantity.' '.$step['quantity_unit'].'; termin UTC: '.($due??'bez terminu').'; '.($n>0&&$step['depends_on_previous']?'po poprzednim etapie':($parent&&$n===0?'po etapie #'.$parent:'bez poprzednika')).'; wykonawcy: '.($workerNames?implode(', ',$workerNames):'do przydzielenia').($step['instructions']!==''?'; instrukcje: '.$step['instructions']:'').($step['parameter_notes']!==''?'; parametry: '.$step['parameter_notes']:'');
 }
 $status=reference('order_statuses',(int)$snapshot['order']['status_id']);if($status['is_terminal']||$status['code']==='ready')$changes[]='Zamówienie wróci do produkcji; data zakończenia zostanie usunięta.';
 $key='template:'.$c['id'].':'.$template['id'];$fingerprint=[$snapshot,$template,$plan];
 if($preview){db()->rollBack();$token=planApproval($key,$data,$fingerprint,$changes,true);return ['changes'=>$changes,'confirmation_token'=>$token];}
 checkPlanToken($key,$data);planApproval($key,$data,$fingerprint,$changes,false);
 $application=insert('component_template_applications',['component_id'=>$c['id'],'template_id'=>$template['id'],'template_version'=>$template['version'],'applied_by'=>$userId,'snapshot'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
 $created=[];$previous=null;
 foreach($plan as $step){$workers=$step['assignee_ids'];$depends=$step['depends_on_previous'];$number=$step['step_number'];unset($step['assignee_ids'],$step['depends_on_previous'],$step['step_number']);
  $id=insert('production_tasks',['order_item_id'=>$item['id'],'component_id'=>$c['id'],'status'=>'pending','template_application_id'=>$application,'template_step_number'=>$number,...$step]);
  foreach($workers as $uid)insert('task_assignments',['task_id'=>$id,'user_id'=>$uid,'assigned_by'=>$userId]);
  $predecessor=$previous&&$depends?$previous:(!$previous?$parent:null);if($predecessor)insert('task_dependencies',['task_id'=>$id,'depends_on_task_id'=>$predecessor]);
  insert('task_status_history',['task_id'=>$id,'new_status'=>'pending','event_type'=>'created','changed_by'=>$userId,'reason'=>'Szablon „'.$template['name'].'” v'.$template['version'].', etap '.$number]);$previous=$id;$created[]=$id;
 }
 reopenTaskOrder($snapshot['order'],$userId,'Zastosowano szablon etapów dla części '.$c['name']);unset($_SESSION['production_approvals'][$key]);db()->commit();return ['task_ids'=>$created,'application_id'=>$application];
}
