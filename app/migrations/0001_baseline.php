<?php
declare(strict_types=1);
// Niezmienna migracja bazowa wydania produkcyjnego 1.0.0.
return ['version'=>1,'release'=>'1.0.0','name'=>'Schemat bazowy Arkusz 1.0.0','up'=>static function(): void {
 db()->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS theme ENUM('green','beige','gray','black','beige_light') NOT NULL DEFAULT 'green'");
 $column=one("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='theme'");
 if(!str_contains($column['COLUMN_TYPE'],'beige_light'))db()->exec("ALTER TABLE users MODIFY COLUMN theme ENUM('green','beige','gray','black','beige_light') NOT NULL DEFAULT 'green'");
 $expected=json_decode(<<<'JSON'
{
  "users": [
    "id",
    "login",
    "display_name",
    "password_hash",
    "theme",
    "is_active",
    "created_at"
  ],
  "customers": [
    "id",
    "name",
    "tax_id",
    "email",
    "phone",
    "address_text",
    "notes",
    "is_active",
    "created_at",
    "updated_at"
  ],
  "product_types": [
    "id",
    "code",
    "name",
    "is_active"
  ],
  "paper_types": [
    "id",
    "name",
    "description",
    "is_active"
  ],
  "paper_variants": [
    "id",
    "paper_type_id",
    "name",
    "grammage_gsm",
    "manufacturer",
    "color",
    "description",
    "is_active"
  ],
  "order_statuses": [
    "id",
    "code",
    "name",
    "is_terminal",
    "sort_order",
    "is_active"
  ],
  "orders": [
    "id",
    "order_number",
    "customer_id",
    "customer_name",
    "customer_tax_id",
    "customer_address",
    "contact_name",
    "contact_email",
    "contact_phone",
    "title",
    "status_id",
    "priority",
    "currency",
    "ordered_at",
    "due_at",
    "completed_at",
    "delivery_method",
    "delivery_address",
    "customer_notes",
    "internal_notes",
    "created_by",
    "updated_at",
    "version"
  ],
  "order_items": [
    "id",
    "order_id",
    "line_number",
    "product_type_id",
    "product_type_name",
    "name",
    "quantity",
    "finished_width_mm",
    "finished_height_mm",
    "agreed_total_net",
    "tax_rate",
    "specification_notes"
  ],
  "item_components": [
    "id",
    "order_item_id",
    "name",
    "sort_order",
    "page_count",
    "paper_variant_id",
    "paper_description",
    "grammage_gsm",
    "print_technique",
    "offset_front_colors",
    "offset_back_colors",
    "digital_front_mode",
    "digital_back_mode",
    "bleed_mm",
    "notes"
  ],
  "component_inks": [
    "id",
    "component_id",
    "side",
    "ink_number",
    "ink_name"
  ],
  "operation_types": [
    "id",
    "code",
    "name",
    "is_active"
  ],
  "production_tasks": [
    "id",
    "order_item_id",
    "component_id",
    "operation_type_id",
    "operation_name",
    "sequence_number",
    "status",
    "work_center_id",
    "planned_quantity",
    "quantity_unit",
    "planned_start_at",
    "due_at",
    "started_at",
    "finished_at",
    "parameters",
    "instructions",
    "version"
  ],
  "production_reports": [
    "id",
    "task_id",
    "reported_by",
    "reported_at",
    "good_quantity",
    "waste_quantity",
    "work_minutes",
    "status_after",
    "notes"
  ],
  "order_status_history": [
    "id",
    "order_id",
    "old_status_id",
    "new_status_id",
    "changed_by",
    "changed_at",
    "reason"
  ],
  "order_files": [
    "id",
    "order_id",
    "order_item_id",
    "original_name",
    "storage_key",
    "mime_type",
    "size_bytes",
    "sha256",
    "purpose",
    "revision",
    "uploaded_by",
    "uploaded_at",
    "approved_by",
    "approved_at",
    "approval_note"
  ],
  "roles": [
    "id",
    "code",
    "name"
  ],
  "user_roles": [
    "user_id",
    "role_id"
  ],
  "permissions": [
    "id",
    "code",
    "name"
  ],
  "role_permissions": [
    "role_id",
    "permission_id"
  ],
  "work_centers": [
    "id",
    "code",
    "name",
    "is_active"
  ],
  "user_work_centers": [
    "user_id",
    "work_center_id"
  ],
  "task_assignments": [
    "task_id",
    "user_id",
    "assigned_by",
    "assigned_at"
  ],
  "task_dependencies": [
    "task_id",
    "depends_on_task_id"
  ],
  "task_status_history": [
    "id",
    "task_id",
    "old_status",
    "new_status",
    "event_type",
    "changed_by",
    "changed_at",
    "reason"
  ],
  "feature_types": [
    "id",
    "code",
    "name",
    "scope",
    "description",
    "is_active"
  ],
  "feature_parameter_definitions": [
    "id",
    "feature_type_id",
    "code",
    "name",
    "value_type",
    "unit",
    "is_required"
  ],
  "item_features": [
    "id",
    "order_item_id",
    "feature_type_id",
    "notes"
  ],
  "component_features": [
    "id",
    "component_id",
    "feature_type_id",
    "notes"
  ],
  "item_feature_values": [
    "item_feature_id",
    "parameter_definition_id",
    "value_text",
    "value_number",
    "value_boolean"
  ],
  "component_feature_values": [
    "component_feature_id",
    "parameter_definition_id",
    "value_text",
    "value_number",
    "value_boolean"
  ],
  "task_item_features": [
    "task_id",
    "item_feature_id"
  ],
  "task_component_features": [
    "task_id",
    "component_feature_id"
  ]
}
JSON,true,512,JSON_THROW_ON_ERROR);
 foreach($expected as $table=>$columns){
  $actual=array_column(rows('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]),'COLUMN_NAME');
  foreach($columns as $column)if(!in_array($column,$actual,true))throw new RuntimeException('Brak kolumny schematu 1.0: '.$table.'.'.$column);
 }
}];
