#!/usr/bin/env python3
"""Wyłącznie izolowana baza: testuje również celowe awarie migracji."""
import argparse,json,os,pathlib,shlex,subprocess,urllib.error,urllib.request
p=argparse.ArgumentParser();p.add_argument('--engine',default='docker compose');p.add_argument('--project',default='arkusz-release-test');p.add_argument('--url',default='http://127.0.0.1:18080');args=p.parse_args()
if args.project=='arkusz':raise SystemExit('Nie testuj migracji na produkcji.')
command=shlex.split(args.engine)+['-p',args.project]
def compose(*items):return subprocess.check_output(command+list(items),stderr=subprocess.PIPE)
config=json.loads(compose('config','--format','json'));root=pathlib.Path(config['services']['setup']['environment']['APP_DATA_HOST_PATH'])/'app'
if root.parent.resolve()==(pathlib.Path.cwd()/'app_data').resolve():raise SystemExit('Nie testuj migracji na app_data instalacji użytkowej.')
def php(code):return compose('exec','-T','web','php','-r',code)
def sql(query):return php('require "/var/www/db.php";db()->exec('+json.dumps(query)+');')
def migrate(success=True):
 result=subprocess.run(command+['exec','-T','web','php','/var/www/migrate.php'],capture_output=True)
 assert (result.returncode==0)==success,(result.returncode,result.stderr.decode())
def health(expected):
 try:
  with urllib.request.urlopen(args.url+'/health.php') as r:code=r.status
 except urllib.error.HTTPError as e:code=e.code
 assert code==expected,(code,expected)
counts=php('require "/var/www/db.php";echo json_encode([one("SELECT COUNT(*) n FROM orders"),one("SELECT COUNT(*) n FROM users"),one("SELECT COUNT(*) n FROM production_reports")]);')
baseline=root/'migrations/0001_baseline.php';original=baseline.read_bytes();release=root/'release.json';original_release=release.read_bytes();current=json.loads(original_release);next_version=int(current['schema_version'])+1;next_release_version='1.1.1';fixture=root/f'migrations/{next_version:04d}_test.php'
migrate();migrate();health(200)
try:
 baseline.write_bytes(original+b'\n// Zmieniony zastosowany plik testowy.\n');migrate(False);health(503)
finally:baseline.write_bytes(original)
for status in ['running','failed']:
 sql("UPDATE schema_migrations SET status='"+status+"' WHERE version=1");migrate(False);health(503)
 sql("UPDATE schema_migrations SET status='applied' WHERE version=1")
health(200)
# Symulowana kolejna wersja tylko na kopii aplikacji i bazy testowej.
try:
 fixture.write_text(('''<?php
return ['version'=>NEXT_VERSION,'release'=>'NEXT_RELEASE','name'=>'Test kolejnego wydania','up'=>static function(): void {
 db()->exec("CREATE TABLE IF NOT EXISTS migration_probe(id INT PRIMARY KEY)");
 db()->exec("INSERT INTO migration_probe(id) VALUES(1)");
}];
''').replace('NEXT_VERSION',str(next_version)).replace('NEXT_RELEASE',next_release_version))
 next_release=json.loads(original_release);next_release.update(version=next_release_version,schema_version=next_version);release.write_text(json.dumps(next_release))
 migrate();migrate();health(200)
 assert php('require "/var/www/db.php";echo one("SELECT COUNT(*) n FROM migration_probe")["n"];')==b'1'
 # Stary program nie może zostać uruchomiony na zaktualizowanej bazie.
 release.write_bytes(original_release);fixture.unlink();migrate(False);health(503)
finally:
 release.write_bytes(original_release);fixture.unlink(missing_ok=True)
 sql('DROP TABLE IF EXISTS migration_probe');sql('DELETE FROM schema_migrations WHERE version='+str(next_version))
# Rzeczywisty błąd po DDL pozostawia failed, a nie udaje rollbacku.
try:
 fixture.write_text(('''<?php
return ['version'=>NEXT_VERSION,'release'=>'NEXT_RELEASE','name'=>'Test przerwanej zmiany','up'=>static function(): void {
 db()->exec("CREATE TABLE IF NOT EXISTS migration_failure_probe(id INT PRIMARY KEY)");
 throw new RuntimeException('Kontrolowany błąd testowy po DDL');
}];
''').replace('NEXT_VERSION',str(next_version)).replace('NEXT_RELEASE',next_release_version))
 next_release=json.loads(original_release);next_release.update(version=next_release_version,schema_version=next_version);release.write_text(json.dumps(next_release))
 migrate(False);health(503)
 assert php('require "/var/www/db.php";echo one("SELECT status FROM schema_migrations WHERE version='+str(next_version)+'")["status"];')==b'failed'
 migrate(False)
finally:
 release.write_bytes(original_release);fixture.unlink(missing_ok=True)
 sql('DROP TABLE IF EXISTS migration_failure_probe');sql('DELETE FROM schema_migrations WHERE version='+str(next_version))
migrate();health(200)
assert counts==php('require "/var/www/db.php";echo json_encode([one("SELECT COUNT(*) n FROM orders"),one("SELECT COUNT(*) n FROM users"),one("SELECT COUNT(*) n FROM production_reports")]);')
print('OK: idempotencja, sumy kontrolne, blokada running/failed, symulowana aktualizacja kolejnego wydania, blokada cofnięcia i zachowanie danych.')
