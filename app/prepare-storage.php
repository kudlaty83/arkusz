<?php
declare(strict_types=1);
$host=getenv('APP_DATA_HOST_PATH')?:'';
if(!str_starts_with($host,'/')&&!preg_match('~^[A-Za-z]:[\\\\/]~',$host))throw new RuntimeException('APP_DATA_DIR musi być bezwzględną ścieżką na dysku.');
require __DIR__.'/bootstrap-secrets.php';
$target='/opt/arkusz-app';
if(!is_dir($target)||is_link($target))throw new RuntimeException('Brak katalogu plików aplikacji.');
$lock=fopen($target.'/.arkusz-lock','c');
if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Nie można zablokować katalogu aplikacji.');
function managedPath(string $root,string $relative): string {
 if($relative===''||str_starts_with($relative,'/')||str_contains($relative,'\\')||in_array('..',explode('/',$relative),true))throw new RuntimeException('Nieprawidłowa ścieżka pliku aplikacji.');
 $path=$root;
 foreach(explode('/',$relative) as $part){$path.='/'.$part;if(is_link($path))throw new RuntimeException('Dowiązanie w katalogu aplikacji nie jest obsługiwane.');}
 return $path;
}
try {
 chmod($target,0755);$files=[];
 $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__,FilesystemIterator::SKIP_DOTS));
 foreach($iterator as $file){
  if(!$file->isFile()||$file->isLink())continue;
  $relative=substr($file->getPathname(),strlen(__DIR__)+1);$destination=managedPath($target,$relative);
  if(!is_dir(dirname($destination))&&!mkdir(dirname($destination),0755,true))throw new RuntimeException('Nie można utworzyć katalogu aplikacji.');
  $temp=$destination.'.arkusz-new';
  if(is_link($temp)||!copy($file->getPathname(),$temp))throw new RuntimeException('Nie można skopiować pliku aplikacji.');
  chmod($temp,0644);
  if(!rename($temp,$destination))throw new RuntimeException('Nie można zapisać pliku aplikacji.');
  $files[]=$relative;
 }
 $manifest=$target.'/.arkusz-files.json';
 if(is_link($manifest))throw new RuntimeException('Nieprawidłowy manifest aplikacji.');
 $previous=is_file($manifest)?json_decode(file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR):[];
 foreach(array_diff($previous,$files) as $old){$path=managedPath($target,$old);if(is_file($path)&&!unlink($path))throw new RuntimeException('Nie można usunąć nieaktualnego pliku aplikacji.');}
 file_put_contents($manifest,json_encode($files,JSON_THROW_ON_ERROR));chmod($manifest,0644);
 $sessions='/opt/arkusz-sessions';
 if(!is_dir($sessions)||is_link($sessions)||!chown($sessions,'www-data')||!chgrp($sessions,'www-data')||!chmod($sessions,0700))throw new RuntimeException('Nie można przygotować katalogu sesji PHP.');
 echo "Pliki aplikacji i sesje są przygotowane w katalogach na dysku.\n";
}finally{flock($lock,LOCK_UN);fclose($lock);}
