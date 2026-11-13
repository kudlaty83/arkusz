<?php
declare(strict_types=1);
return ['version'=>4,'release'=>'1.2.0','name'=>'Edycja klientów i kont, paginacja oraz wersjonowanie sesji','up'=>static function(): void {
 db()->exec('ALTER TABLE customers ADD COLUMN IF NOT EXISTS version INT UNSIGNED NOT NULL DEFAULT 1');
 db()->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS version INT UNSIGNED NOT NULL DEFAULT 1, ADD COLUMN IF NOT EXISTS auth_version INT UNSIGNED NOT NULL DEFAULT 1');
 foreach(['idx_customers_active_name'=>'(is_active,name,id)','idx_customers_email'=>'(email)','idx_customers_phone'=>'(phone)'] as $name=>$columns)db()->exec("CREATE INDEX IF NOT EXISTS $name ON customers $columns");
 db()->exec('CREATE INDEX IF NOT EXISTS idx_users_active_name ON users(is_active,display_name,id)');
}];
