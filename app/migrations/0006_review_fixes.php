<?php
declare(strict_types=1);
return ['version'=>6,'release'=>'1.4.0','name'=>'Spójne anulowanie, trwałe podstawy ilości i limit logowania','up'=>static function(): void {
 require_once dirname(__DIR__).'/production-state.php';
 db()->exec("ALTER TABLE production_tasks
  ADD COLUMN IF NOT EXISTS quantity_mode ENUM('legacy','per_copy','fixed') NOT NULL DEFAULT 'legacy',
  ADD COLUMN IF NOT EXISTS quantity_base_quantity BIGINT UNSIGNED NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS quantity_base_copies INT UNSIGNED NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS quantity_review_required BOOLEAN NOT NULL DEFAULT TRUE");
 db()->exec("CREATE TABLE IF NOT EXISTS login_attempt_limits (
  bucket_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  bucket_kind VARCHAR(20) NOT NULL,
  window_started BIGINT UNSIGNED NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  INDEX idx_login_limits_expiry(expires_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 // Istniejące ilości i raporty pozostają niezmienione. Nie zgadujemy trybu z nazwy jednostki.
 foreach(rows('SELECT t.*,i.quantity copies,a.snapshot template_snapshot FROM production_tasks t JOIN order_items i ON i.id=t.order_item_id LEFT JOIN component_template_applications a ON a.id=t.template_application_id ORDER BY t.id') as $task){
  $rule=['quantity_mode'=>'legacy','quantity_base_quantity'=>(int)$task['planned_quantity'],'quantity_base_copies'=>(int)$task['copies'],'quantity_review_required'=>1];
  $manual=false;
  foreach(rows('SELECT previous_snapshot,accepted_plan FROM task_plan_changes WHERE task_id=? ORDER BY id',[$task['id']]) as $audit){
   $before=json_decode($audit['previous_snapshot'],true,512,JSON_THROW_ON_ERROR);$accepted=json_decode($audit['accepted_plan'],true,512,JSON_THROW_ON_ERROR);
   $previous=array_column($before['tasks']??[],null,'id')[$task['id']]??null;
   if(!$previous||!isset($accepted['planned_quantity'],$accepted['quantity_unit'])||(int)$previous['planned_quantity']!==(int)$accepted['planned_quantity']||$previous['quantity_unit']!==$accepted['quantity_unit']){$manual=true;break;}
  }
  if(!$manual&&$task['template_snapshot']){
   $template=json_decode($task['template_snapshot'],true,512,JSON_THROW_ON_ERROR);
   foreach($template['steps']??[] as $step)if((int)$step['step_number']===(int)$task['template_step_number']){
    try{$rule=templateQuantityRule($step);}catch(RuntimeException){/* Niejasny stary zapis pozostaje do weryfikacji. */}break;
   }
  }
  run('UPDATE production_tasks SET quantity_mode=?,quantity_base_quantity=?,quantity_base_copies=?,quantity_review_required=? WHERE id=?',[...array_values($rule),$task['id']]);
 }
 // Naprawa osieroconej produkcji jest jawnie odnotowana; wykonanej pracy nie kasujemy.
 foreach(rows('SELECT o.id,o.created_by,s.code FROM orders o JOIN order_statuses s ON s.id=o.status_id WHERE EXISTS(SELECT 1 FROM order_items i JOIN production_tasks t ON t.order_item_id=i.id WHERE i.order_id=o.id)') as $order){
  $tasks=rows('SELECT t.* FROM production_tasks t JOIN order_items i ON i.id=t.order_item_id WHERE i.order_id=?',[$order['id']]);
  $deps=rows('SELECT d.* FROM task_dependencies d JOIN production_tasks t ON t.id=d.task_id JOIN order_items i ON i.id=t.order_item_id WHERE i.order_id=?',[$order['id']]);
  $seeds=[];foreach($tasks as $task)if($order['code']==='cancelled'||$task['status']==='cancelled')$seeds[]=(int)$task['id'];
  $ids=dependentTaskIds($deps,$seeds);
  if(cancelProductionTasks(['tasks'=>$tasks],$ids,(int)$order['created_by'],'Automatyczna naprawa 1.4.0: zatrzymanie produkcji anulowanego zamówienia lub następników anulowanego etapu.'))run('UPDATE orders SET version=version+1 WHERE id=?',[$order['id']]);
 }
}];
