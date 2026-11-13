<?php
declare(strict_types=1);
require __DIR__.'/db.php';
$ready=false;
for($attempt=0;$attempt<60;$attempt++){
 try {db()->query('SELECT 1');$ready=true;break;}
 catch(PDOException){sleep(1);}
}
if(!$ready)throw new RuntimeException('Baza nie jest dostępna. Sprawdź stan usługi db.');
if((int)one("SELECT GET_LOCK('arkusz-initialize',60) acquired")['acquired']!==1)throw new RuntimeException('Inna inicjalizacja nie została zakończona.');
try {
 if(!one("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users'")){
  $schema=file_get_contents(__DIR__.'/baza.sql');
  if($schema===false)throw new RuntimeException('Brak schematu bazy.');
  db()->exec($schema);
  echo "Utworzono schemat bazy.\n";
 }
 require __DIR__.'/migrate.php';
 require __DIR__.'/seed.php';
}finally{db()->query("SELECT RELEASE_LOCK('arkusz-initialize')");}
