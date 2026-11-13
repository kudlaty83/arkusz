<?php
declare(strict_types=1);
return ['version'=>2,'release'=>'1.1.0','name'=>'Edycja zamówienia z potwierdzeniem planu produkcji','up'=>static function(): void {
 foreach(['order_items','item_components','item_features','component_features'] as $table)db()->exec("ALTER TABLE $table ADD COLUMN IF NOT EXISTS is_archived BOOLEAN NOT NULL DEFAULT FALSE");
 foreach(['production_tasks','production_reports'] as $table)db()->exec("ALTER TABLE $table ADD COLUMN IF NOT EXISTS plan_revision INT UNSIGNED NOT NULL DEFAULT 1");
 db()->exec("ALTER TABLE task_status_history MODIFY COLUMN event_type ENUM('status','assignment','created','plan') NOT NULL DEFAULT 'status'");
 db()->exec("CREATE TABLE IF NOT EXISTS order_change_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  changed_by BIGINT UNSIGNED NOT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  changes JSON NOT NULL,
  previous_snapshot JSON NOT NULL,
  accepted_specification JSON NOT NULL,
  FOREIGN KEY(order_id) REFERENCES orders(id),
  FOREIGN KEY(changed_by) REFERENCES users(id),
  INDEX idx_order_changes(order_id,id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}];
