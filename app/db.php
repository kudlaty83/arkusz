<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
date_default_timezone_set('UTC');
function db(): PDO {
    static $db;
    if (!$db) {
        $db = new PDO('mysql:host='.configValue('DB_HOST','db').';dbname=drukarnia;charset=utf8mb4', configValue('DB_USER','drukarnia'), configValue('DB_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
        $db->exec("SET time_zone = '+00:00'");
    }
    return $db;
}
function rows(string $sql, array $args=[]): array { $s=db()->prepare($sql); $s->execute($args); return $s->fetchAll(); }
function one(string $sql, array $args=[]): ?array { return rows($sql,$args)[0]??null; }
function run(string $sql,array $args=[]): void { $s=db()->prepare($sql); $s->execute($args); }
function insert(string $table,array $data): int {
    $cols=array_keys($data);
    run('INSERT INTO '.$table.' (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')',array_values($data));
    return (int)db()->lastInsertId();
}
