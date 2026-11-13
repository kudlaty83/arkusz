<?php
declare(strict_types=1);
$directory='/run/arkusz-secrets';
if(!is_dir($directory))throw new RuntimeException('Brak katalogu konfiguracji na dysku.');
chmod($directory,0755);
$lock=fopen($directory.'/.lock','c');
if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Nie można zablokować konfiguracji.');
foreach(['db-password','db-root-password','admin-password'] as $name){
 $path=$directory.'/'.$name;
 if(!file_exists($path)){
  $temp=$path.'.new';
  if(file_put_contents($temp,bin2hex(random_bytes(24))."\n")===false)throw new RuntimeException('Nie można zapisać konfiguracji.');
  chmod($temp,$name==='admin-password'?0400:0444);
  if(!rename($temp,$path))throw new RuntimeException('Nie można zatwierdzić konfiguracji.');
 }
 if(trim((string)file_get_contents($path))==='')throw new RuntimeException('Pusty plik konfiguracji.');
}
flock($lock,LOCK_UN);fclose($lock);
echo "Hasła są gotowe. Istniejące hasła pozostały bez zmian.\n";
