<?php
declare(strict_types=1);
return ['version'=>5,'release'=>'1.3.0','name'=>'Edytor etapów i szablony produkcji dla rodzajów części','up'=>static function(): void {
 db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS component_types (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(50) NOT NULL UNIQUE,
 name VARCHAR(150) NOT NULL,
 description TEXT,
 is_active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS production_templates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(200) NOT NULL,
 component_type_id BIGINT UNSIGNED NOT NULL,
 product_type_id BIGINT UNSIGNED NULL,
 print_technique ENUM('offset','digital') NULL,
 description TEXT,
 is_active BOOLEAN NOT NULL DEFAULT TRUE,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(component_type_id) REFERENCES component_types(id),
 FOREIGN KEY(product_type_id) REFERENCES product_types(id),
 FOREIGN KEY(created_by) REFERENCES users(id),
 INDEX idx_templates_active_name(is_active,name,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS production_template_steps (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 template_id BIGINT UNSIGNED NOT NULL,
 step_number SMALLINT UNSIGNED NOT NULL,
 operation_type_id BIGINT UNSIGNED NOT NULL,
 work_center_id BIGINT UNSIGNED NOT NULL,
 quantity_mode ENUM('per_copy','fixed') NOT NULL DEFAULT 'per_copy',
 quantity_value DECIMAL(12,4) NOT NULL DEFAULT 1 CHECK(quantity_value>0),
 quantity_unit VARCHAR(30) NOT NULL,
 days_before_due SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 depends_on_previous BOOLEAN NOT NULL DEFAULT TRUE,
 instructions TEXT,
 parameter_notes TEXT,
 FOREIGN KEY(template_id) REFERENCES production_templates(id),
 FOREIGN KEY(operation_type_id) REFERENCES operation_types(id),
 FOREIGN KEY(work_center_id) REFERENCES work_centers(id),
 UNIQUE(template_id,step_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS component_template_applications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 component_id BIGINT UNSIGNED NOT NULL,
 template_id BIGINT UNSIGNED NOT NULL,
 template_version INT UNSIGNED NOT NULL,
 applied_by BIGINT UNSIGNED NOT NULL,
 applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 snapshot JSON NOT NULL,
 FOREIGN KEY(component_id) REFERENCES item_components(id),
 FOREIGN KEY(template_id) REFERENCES production_templates(id),
 FOREIGN KEY(applied_by) REFERENCES users(id),
 UNIQUE(component_id,template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS task_plan_changes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 task_id BIGINT UNSIGNED NOT NULL,
 changed_by BIGINT UNSIGNED NOT NULL,
 changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 changes JSON NOT NULL,
 previous_snapshot JSON NOT NULL,
 accepted_plan JSON NOT NULL,
 FOREIGN KEY(task_id) REFERENCES production_tasks(id),
 FOREIGN KEY(changed_by) REFERENCES users(id),
 INDEX(task_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SQL);
 db()->exec('ALTER TABLE item_components ADD COLUMN IF NOT EXISTS component_type_id BIGINT UNSIGNED NULL');
 db()->exec('ALTER TABLE production_tasks ADD COLUMN IF NOT EXISTS template_application_id BIGINT UNSIGNED NULL, ADD COLUMN IF NOT EXISTS template_step_number SMALLINT UNSIGNED NULL');
 foreach([['item_components','fk_component_type','component_type_id','component_types'],['production_tasks','fk_task_template_application','template_application_id','component_template_applications']] as [$table,$constraint,$column,$target]){
  if(!one('SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?',[$table,$constraint]))db()->exec("ALTER TABLE $table ADD CONSTRAINT $constraint FOREIGN KEY($column) REFERENCES $target(id)");
 }
 db()->exec("INSERT INTO component_types(code,name,description) VALUES ('cover','Okładka','Okładka książki lub broszury'),('body','Środek','Wnętrze książki lub broszury'),('insert','Wkładka','Dodatkowa wkładka'),('whole','Całość','Jednoczęściowy produkt') ON DUPLICATE KEY UPDATE code=VALUES(code);");
}];
