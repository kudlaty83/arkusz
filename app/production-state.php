<?php
declare(strict_types=1);
function dependentTaskIds(array $dependencies,array $seeds): array {
 $ids=array_fill_keys(array_map('intval',$seeds),true);
 do{$again=false;foreach($dependencies as $edge)if(isset($ids[(int)$edge['depends_on_task_id']])&&!isset($ids[(int)$edge['task_id']])){$ids[(int)$edge['task_id']]=true;$again=true;}}while($again);
 return array_map('intval',array_keys($ids));
}
function cancelledProductionOrder(array $order): bool {
 return (one('SELECT code FROM order_statuses WHERE id=?',[$order['status_id']])['code']??'')==='cancelled';
}
function requireProductionOpen(array $order): void {
 if(cancelledProductionOrder($order))fail('Zamówienie jest anulowane. Najpierw jawnie zmień jego status, aby zaplanować lub raportować pracę.');
}
function cancellationChanges(array $snapshot,array $ids,string $reason): array {
 $changes=[];foreach($snapshot['tasks'] as $task)if(in_array((int)$task['id'],$ids,true)&&!in_array($task['status'],['done','cancelled'],true))$changes[]='Etap #'.$task['id'].' '.$task['operation_name'].': '.$task['status'].' → anulowany; '.$reason.'. Raporty pozostaną w historii.';
 return $changes;
}
function cancelProductionTasks(array $snapshot,array $ids,int $actor,string $reason): int {
 $count=0;foreach($snapshot['tasks'] as $task)if(in_array((int)$task['id'],$ids,true)&&!in_array($task['status'],['done','cancelled'],true)){
  run("UPDATE production_tasks SET status='cancelled',version=version+1 WHERE id=?",[$task['id']]);
  insert('task_status_history',['task_id'=>$task['id'],'old_status'=>$task['status'],'new_status'=>'cancelled','event_type'=>'status','changed_by'=>$actor,'reason'=>$reason]);$count++;
 }return $count;
}
function taskQuantityForCopies(array $task,int $copies): int {
 $mode=$task['quantity_mode']??'legacy';
 if($mode==='legacy'||(int)($task['quantity_review_required']??0))fail('Etap #'.($task['id']??'?').' wymaga wyboru sposobu liczenia ilości w edytorze etapu przed zmianą nakładu.');
 $base=(int)$task['quantity_base_quantity'];$den=(int)$task['quantity_base_copies'];
 if($base<1||$den<1||$copies<1)throw new RuntimeException('Nieprawidłowa podstawa ilości etapu.');
 if($mode==='fixed'){$copies=1;$den=1;}
 if($base>intdiv(100000000*$den,$copies))fail('Przeliczony plan etapu przekracza dopuszczalną ilość.');
 return intdiv($base*$copies+$den-1,$den);
}
function templateQuantityRule(array $step): array {
 $mode=$step['quantity_mode'];$value=(string)$step['quantity_value'];
 if(!in_array($mode,['fixed','per_copy'],true)||!preg_match('/^(\d{1,8})(?:\.(\d{1,4}))?$/D',$value,$m))throw new RuntimeException('Nieprawidłowa podstawa ilości szablonu.');
 $scaled=(int)$m[1]*10000+(int)str_pad($m[2]??'',4,'0');
 if($scaled<1||($mode==='fixed'&&$scaled%10000!==0))throw new RuntimeException('Nieprawidłowa stała ilość szablonu.');
 return ['quantity_mode'=>$mode,'quantity_base_quantity'=>$mode==='fixed'?intdiv($scaled,10000):$scaled,'quantity_base_copies'=>$mode==='fixed'?1:10000,'quantity_review_required'=>0];
}
function manualQuantityRule(array $data,int $quantity,int $copies,?array $old): array {
 $mode=$data['quantity_mode']??($old['quantity_mode']??'per_copy');
 if(!in_array($mode,['fixed','per_copy'],true))fail('Wybierz ilość stałą albo proporcjonalną w edytorze etapu.');
 $reset=!$old||$old['quantity_mode']==='legacy'||!empty($data['reset_quantity_basis'])||$old['quantity_mode']!==$mode||(int)$old['planned_quantity']!==$quantity||$old['quantity_unit']!==trim((string)($data['quantity_unit']??''));
 if(!$reset)return [...array_intersect_key($old,array_flip(['quantity_mode','quantity_base_quantity','quantity_base_copies'])), 'quantity_review_required'=>0];
 return ['quantity_mode'=>$mode,'quantity_base_quantity'=>$quantity,'quantity_base_copies'=>$mode==='fixed'?1:$copies,'quantity_review_required'=>0];
}
