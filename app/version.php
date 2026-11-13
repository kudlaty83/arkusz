<?php
declare(strict_types=1);
function releaseInfo(): array {
 static $release;
 return $release??=json_decode(file_get_contents(__DIR__.'/release.json'),true,32,JSON_THROW_ON_ERROR);
}
function schemaInfo(): array {
 $rows=rows('SELECT version,release_version,name,checksum,status,applied_at FROM schema_migrations ORDER BY version');
 $version=0;$release=null;
 foreach($rows as $row){
  if($row['status']!=='applied')throw new RuntimeException('Migracja wymaga interwencji administratora.');
  if((int)$row['version']!==$version+1)throw new RuntimeException('Nieciągła historia migracji.');
  $files=glob(__DIR__.'/migrations/'.sprintf('%04d',(int)$row['version']).'_*.php');
  if(count($files)!==1||!hash_equals($row['checksum'],hash_file('sha256',$files[0])))throw new RuntimeException('Niezgodna suma kontrolna migracji.');
  $version=(int)$row['version'];$release=$row['release_version'];
 }
 return ['schema_version'=>$version,'schema_release'=>$release,'required_schema_version'=>releaseInfo()['schema_version']];
}
function assertSchemaCompatible(): void {
 $schema=schemaInfo();
 if($schema['schema_version']!==(int)releaseInfo()['schema_version']||$schema['schema_release']!==releaseInfo()['version'])throw new RuntimeException('Wersja schematu nie pasuje do programu. Uruchom migracje właściwego wydania.');
}
function versionInfo(): array {
 return ['application'=>releaseInfo(),'database'=>array_merge(['engine'=>'MariaDB','server_version'=>one('SELECT VERSION() version')['version']],schemaInfo()),'runtime'=>['php'=>PHP_VERSION]];
}
