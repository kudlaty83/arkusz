<?php
declare(strict_types=1);
function saveOrderStatus(array $data,int $actor,bool $preview): array {
 permit('orders.edit');db()->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');db()->beginTransaction();$id=integer($data,'id');
 $order=one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id])??fail('Brak zamówienia.',404);
 if((int)$order['version']!==integer($data,'version'))fail('Zamówienie zmieniła inna osoba. Odśwież widok.',409);
 $snapshot=orderPlanSnapshot($id);$status=activeReference('order_statuses',integer($data,'status_id'));$reason=text($data,'reason',5000,false);
 $closing=in_array($status['code'],['ready','delivered'],true)||($status['is_terminal']&&$status['code']!=='cancelled');
 if($closing)foreach($snapshot['tasks'] as $t)if(!in_array($t['status'],['done','cancelled'],true))fail('Najpierw zakończ zadania produkcyjne.');
 $ids=$status['code']==='cancelled'?array_map('intval',array_column($snapshot['tasks'],'id')):[];
 $changes=['Status zamówienia → '.$status['name'],...cancellationChanges($snapshot,$ids,'Anulowanie zamówienia')];
 if(count($changes)>1)permit('tasks.manage');
 if(cancelledProductionOrder($order)&&$status['code']!=='cancelled')$changes[]='Dotychczasowe anulowane etapy pozostaną w historii. Nową produkcję zaplanuj osobno.';
 $key='order-status:'.$id;
 if($preview){db()->rollBack();return ['changes'=>$changes,'confirmation_token'=>planApproval($key,$data,[$snapshot,$status],$changes,true)];}
 if($status['code']==='cancelled'){checkPlanToken($key,$data);planApproval($key,$data,[$snapshot,$status],$changes,false);}
 cancelProductionTasks($snapshot,$ids,$actor,'Anulowanie zamówienia. '.$reason);
 run('UPDATE orders SET status_id=?,completed_at=?,version=version+1 WHERE id=?',[$status['id'],$closing?gmdate('Y-m-d H:i:s'):null,$id]);
 insert('order_status_history',['order_id'=>$id,'old_status_id'=>$order['status_id'],'new_status_id'=>$status['id'],'changed_by'=>$actor,'reason'=>$reason]);
 unset($_SESSION['production_approvals'][$key]);db()->commit();return ['ok'=>true];
}
function saveProductionReport(array $data,int $actor,bool $preview): array {
 permit('tasks.report');db()->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');db()->beginTransaction();$existing=reference('production_tasks',integer($data,'task_id'));
 [$item,$snapshot]=lockTaskOrder((int)$existing['order_item_id']);$t=reference('production_tasks',(int)$existing['id']);taskAccess($t);
 if((int)$t['version']!==integer($data,'version'))fail('Zadanie zostało zmienione. Odśwież widok.',409);
 $status=$data['status_after']??'';$transitions=['pending'=>['in_progress','blocked','cancelled'],'ready'=>['in_progress','blocked','cancelled'],'in_progress'=>['in_progress','blocked','done','cancelled'],'blocked'=>['blocked','in_progress','cancelled'],'done'=>[],'cancelled'=>[]];
 if(!in_array($status,$transitions[$t['status']]??[],true))fail('Niedozwolona zmiana statusu. Najpierw rozpocznij zadanie.');
 if($status==='cancelled')permit('tasks.manage');
 if($preview&&$status!=='cancelled')fail('Podgląd raportu dotyczy anulowania etapu.');
 $byId=array_column($snapshot['tasks'],null,'id');foreach($snapshot['task_dependencies'] as $edge)if((int)$edge['task_id']===(int)$t['id']&&in_array($status,['in_progress','done'],true)&&$byId[$edge['depends_on_task_id']]['status']!=='done')fail('Najpierw zakończ zadania poprzedzające.');
 $good=integer($data,'good_quantity',0);$waste=integer($data,'waste_quantity',0);$minutes=integer($data,'work_minutes',0,100000);$notes=text($data,'notes',10000,false);
 if($status==='blocked'&&$notes==='')fail('Opisz przyczynę blokady.');
 $total=$good;foreach($snapshot['reports'] as $r)if((int)$r['task_id']===(int)$t['id']&&(int)$r['plan_revision']===(int)$t['plan_revision'])$total+=(int)$r['good_quantity'];
 if($status==='done'&&$total<(int)$t['planned_quantity'])fail('Wykonana ilość jest mniejsza od planowanej.');
 $ids=$status==='cancelled'?dependentTaskIds($snapshot['task_dependencies'],[(int)$t['id']]):[];
 $changes=cancellationChanges($snapshot,$ids,'Anulowanie etapu #'.$t['id']);
 if($status==='cancelled')$changes[]='Zapis raportu: '.$good.' dobrych, '.$waste.' odpadu, '.$minutes.' minut. Zakończone etapy i wcześniejsze raporty pozostaną zachowane.';
 $key='cancel-task:'.$t['id'];
 if($preview){db()->rollBack();return ['changes'=>$changes,'confirmation_token'=>planApproval($key,$data,$snapshot,$changes,true)];}
 if($status==='cancelled'){checkPlanToken($key,$data);planApproval($key,$data,$snapshot,$changes,false);}
 insert('production_reports',['task_id'=>$t['id'],'plan_revision'=>$t['plan_revision'],'reported_by'=>$actor,'good_quantity'=>$good,'waste_quantity'=>$waste,'work_minutes'=>$minutes,'status_after'=>$status,'notes'=>$notes]);
 if($status==='cancelled')cancelProductionTasks($snapshot,$ids,$actor,'Anulowanie etapu #'.$t['id'].'. '.$notes);
 else{
  run("UPDATE production_tasks SET status=?,version=version+1,started_at=IF(?='in_progress',COALESCE(started_at,UTC_TIMESTAMP()),started_at),finished_at=IF(?='done',UTC_TIMESTAMP(),NULL) WHERE id=?",[$status,$status,$status,$t['id']]);
  insert('task_status_history',['task_id'=>$t['id'],'old_status'=>$t['status'],'new_status'=>$status,'changed_by'=>$actor,'reason'=>$notes]);
 }
 unset($_SESSION['production_approvals'][$key]);db()->commit();return ['ok'=>true];
}
