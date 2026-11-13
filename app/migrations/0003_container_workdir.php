<?php
declare(strict_types=1);
return ['version'=>3,'release'=>'1.1.1','name'=>'Katalog roboczy kontenera — walidacja schematu bez DDL','up'=>static function(): void {
 foreach(['order_items'=>'is_archived','item_components'=>'is_archived','item_features'=>'is_archived','component_features'=>'is_archived','production_tasks'=>'plan_revision','production_reports'=>'plan_revision','order_change_history'=>'previous_snapshot'] as $table=>$column){
  if(!one('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$table,$column]))throw new RuntimeException('Brak wymaganego elementu schematu: '.$table.'.'.$column);
 }
}];
