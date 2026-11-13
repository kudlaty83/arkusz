<?php
declare(strict_types=1);
$directory='/run/arkusz-secrets';
function secretPath(string $path): void {if(is_link($path))throw new RuntimeException('Dowiązanie w katalogu sekretów jest niedozwolone.');}
function protectSecret(string $path,int $uid,int $gid): void {
 if(!chown($path,$uid)||!chgrp($path,$gid)||!chmod($path,0400))throw new RuntimeException('Nie można ograniczyć praw pliku sekretu.');
}
function secretDirectory(string $path,int $uid=0,int $gid=0): void {
 secretPath($path);if(!is_dir($path)&&!mkdir($path,0700))throw new RuntimeException('Nie można utworzyć katalogu sekretów.');
 if(!chown($path,$uid)||!chgrp($path,$gid)||!chmod($path,0700))throw new RuntimeException('Nie można zabezpieczyć katalogu sekretów.');
}
secretDirectory($directory);secretPath($directory.'/.lock');$lock=fopen($directory.'/.lock','c');
if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Nie można zablokować konfiguracji.');protectSecret($directory.'/.lock',0,0);
try {
 $values=[];
 foreach(['db-password','db-root-password','admin-password'] as $name){
  $path=$directory.'/'.$name;secretPath($path);
  if(!file_exists($path)){
   $temp=$path.'.new';secretPath($temp);if(file_put_contents($temp,bin2hex(random_bytes(24))."\n")===false)throw new RuntimeException('Nie można zapisać konfiguracji.');
   protectSecret($temp,0,0);if(!rename($temp,$path))throw new RuntimeException('Nie można zatwierdzić konfiguracji.');
  }
  if(!is_file($path))throw new RuntimeException('Sekret nie jest zwykłym plikiem.');protectSecret($path,0,0);
  $values[$name]=file_get_contents($path);if($values[$name]===false||trim($values[$name])==='')throw new RuntimeException('Pusty plik konfiguracji.');
 }
 $dbUid=(int)(getenv('DB_SECRET_UID')?:999);$dbGid=(int)(getenv('DB_SECRET_GID')?:999);if($dbUid<1||$dbGid<1)throw new RuntimeException('Nieprawidłowy UID/GID procesu MariaDB.');
 foreach(['web'=>[33,33,['db-password']],'init'=>[0,0,['db-password','admin-password']],'db'=>[$dbUid,$dbGid,['db-password','db-root-password']]] as $role=>[$uid,$gid,$names]){
  $roleDirectory=$directory.'/'.$role;secretDirectory($roleDirectory,$uid,$gid);
  foreach($names as $name){$target=$roleDirectory.'/'.$name;secretPath($target);
   if(!is_file($target)||file_get_contents($target)!==$values[$name]){
    $temp=$target.'.new';secretPath($temp);if(file_put_contents($temp,$values[$name])===false)throw new RuntimeException('Nie można przygotować sekretu dla kontenera.');protectSecret($temp,$uid,$gid);
    if(!rename($temp,$target))throw new RuntimeException('Nie można zatwierdzić sekretu kontenera.');
   }
   protectSecret($target,$uid,$gid);
  }
 }
 echo "Sekrety rozdzielono dla kontenerów. Istniejące hasła pozostały bez zmian.\n";
}finally{flock($lock,LOCK_UN);fclose($lock);}
