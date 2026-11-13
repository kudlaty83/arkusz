<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
require_once __DIR__.'/version.php';
function migrateDatabase(): void {
 if((int)one("SELECT GET_LOCK('arkusz-schema-migration',60) acquired")['acquired']!==1)throw new RuntimeException('Inna migracja nadal trwa.');
 try {
  db()->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
   version INT UNSIGNED PRIMARY KEY,
   release_version VARCHAR(30) NOT NULL,
   name VARCHAR(150) NOT NULL,
   checksum CHAR(64) NOT NULL,
   status ENUM('running','applied','failed') NOT NULL,
   started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   applied_at DATETIME NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $registry=[];$files=glob(__DIR__.'/migrations/[0-9][0-9][0-9][0-9]_*.php');sort($files);
  foreach($files as $file){
   $migration=require $file;$v=$migration['version'];
   if($v!==count($registry)+1||!is_callable($migration['up']))throw new RuntimeException('Nieprawidłowa kolejność migracji w wydaniu.');
   $registry[$v]=$migration+['checksum'=>hash_file('sha256',$file)];
  }
  $target=(int)releaseInfo()['schema_version'];
  if(count($registry)!==$target)throw new RuntimeException('Wydanie nie zawiera kompletnego zestawu migracji.');
  $applied=rows('SELECT * FROM schema_migrations ORDER BY version');
  foreach($applied as $index=>$row){
   $v=(int)$row['version'];
   if(!isset($registry[$v]))throw new RuntimeException('Baza pochodzi z nowszego wydania. Cofnięcie programu jest zablokowane.');
   if($v!==$index+1||$row['status']!=='applied')throw new RuntimeException('Przerwana lub nieudana migracja. Sprawdź kopię i historię bazy; automatyczna ponowna próba jest zablokowana.');
   if(!hash_equals($row['checksum'],$registry[$v]['checksum'])||$row['release_version']!==$registry[$v]['release'])throw new RuntimeException('Zmieniono już zastosowaną migrację. Przywróć pliki właściwego wydania.');
  }
  foreach($registry as $v=>$migration){
   if($v<=count($applied))continue;
   insert('schema_migrations',['version'=>$v,'release_version'=>$migration['release'],'name'=>$migration['name'],'checksum'=>$migration['checksum'],'status'=>'running']);
   try {
    ($migration['up'])();
    run("UPDATE schema_migrations SET status='applied',applied_at=UTC_TIMESTAMP() WHERE version=?",[$v]);
    echo "Zastosowano migrację $v (wydanie ".$migration['release'].").\n";
   }catch(Throwable $e){
    run("UPDATE schema_migrations SET status='failed' WHERE version=?",[$v]);
    throw $e;
   }
  }
  assertSchemaCompatible();
 }finally{db()->query("SELECT RELEASE_LOCK('arkusz-schema-migration')");}
}
