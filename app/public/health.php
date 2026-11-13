<?php
declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store');
try {
 require dirname(__DIR__).'/db.php';
 require_once dirname(__DIR__).'/version.php';
 assertSchemaCompatible();
 if(!one('SELECT id FROM users LIMIT 1'))throw new RuntimeException('Brak inicjalizacji.');
 echo '{"status":"ok"}';
}catch(Throwable){http_response_code(503);echo '{"status":"unavailable"}';}
