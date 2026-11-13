<?php
declare(strict_types=1);
require_once __DIR__.'/migrations.php';
migrateDatabase();
echo 'Schemat bazy: '.releaseInfo()['schema_version'].', program: '.releaseInfo()['version'].".\n";
