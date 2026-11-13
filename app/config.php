<?php
declare(strict_types=1);
function configValue(string $name,?string $default=null): ?string {
 $value=getenv($name);
 if($value!==false&&$value!=='')return $value;
 $path=getenv($name.'_FILE');
 if($path!==false&&$path!==''){
  $contents=@file_get_contents($path);
  if($contents===false)throw new RuntimeException('Nie można odczytać pliku konfiguracji: '.$name);
  return trim($contents);
 }
 return $default;
}
