<?php
declare(strict_types=1);
// Jedyna lista tabel i kolumn dostępnych przez edytor; identyfikatory od klienta
// nigdy nie trafiają bezpośrednio do zapytania SQL.
function dictionaryDefinitions(): array {
 $name=fn(int $max=150)=>['key'=>'name','label'=>'Nazwa','type'=>'text','required'=>true,'max'=>$max];
 $code=['key'=>'code','label'=>'Kod','type'=>'code','required'=>true,'max'=>50];
 $active=['key'=>'is_active','label'=>'Aktywny','type'=>'boolean','default'=>1];
 $description=['key'=>'description','label'=>'Opis','type'=>'textarea','max'=>10000];
 $ref=fn($key,$label,$table)=>['key'=>$key,'label'=>$label,'type'=>'reference','source'=>$table,'required'=>true];
 return [
  'paper_types'=>['name'=>'Rodzaje papieru','description'=>'Grupy papierów, np. offsetowy, kreda, karton.','fields'=>[$name(),$description,$active]],
  'paper_variants'=>['name'=>'Papiery i gramatury','description'=>'Konkretne papiery dostępne przy opisywaniu części produktu.','fields'=>[$name(200),$ref('paper_type_id','Rodzaj papieru','paper_types'),['key'=>'grammage_gsm','label'=>'Gramatura (g/m²)','type'=>'integer','required'=>true,'min'=>1,'max'=>65535],['key'=>'manufacturer','label'=>'Producent','type'=>'text','max'=>150],['key'=>'color','label'=>'Kolor papieru','type'=>'text','max'=>100],$description,$active]],
  'component_types'=>['name'=>'Rodzaje części','description'=>'Klasyfikacja części, do których przypisuje się typowe etapy produkcji.','fields'=>[$name(),$code,$description,$active]],
  'product_types'=>['name'=>'Rodzaje produktów','description'=>'Wizytówki, książki, ulotki i pozostałe produkty drukarni.','fields'=>[$name(100),$code,$active]],
  'roles'=>['name'=>'Role i uprawnienia','description'=>'Funkcje pracowników oraz dostęp do operacji aplikacji. Rola administratora zachowuje pełny dostęp.','fields'=>[$name(100),$code,['key'=>'permission_ids','label'=>'Uprawnienia','type'=>'permissions']]],
  'work_centers'=>['name'=>'Stanowiska pracy','description'=>'Działy i stanowiska, do których trafiają zadania produkcyjne.','fields'=>[$name(),$code,$active]],
  'operation_types'=>['name'=>'Operacje produkcyjne','description'=>'Rodzaje zadań planowanych przy produkcie i jego częściach.','fields'=>[$name(),$code,$active]],
  'order_statuses'=>['name'=>'Statusy zamówień','description'=>'Nazwy i kolejność statusów. Kody systemowych statusów są stałe.','fields'=>[$name(100),$code,['key'=>'sort_order','label'=>'Kolejność','type'=>'integer','required'=>true,'min'=>-32768,'max'=>32767,'default'=>0],['key'=>'is_terminal','label'=>'Status końcowy','type'=>'boolean','default'=>0],$active]],
  'feature_types'=>['name'=>'Cechy i uszlachetnienia','description'=>'Wielokrotne cechy przypisywane produktom lub ich częściom.','fields'=>[$name(),$code,['key'=>'scope','label'=>'Zakres','type'=>'select','options'=>['both'=>'Produkt i część','item'=>'Cały produkt','component'=>'Część produktu'],'default'=>'both'],$description,$active]],
  'feature_parameter_definitions'=>['name'=>'Parametry cech','description'=>'Pola opisujące uszlachetnienie: rodzaj, strona, obszar, wymiary i inne.','fields'=>[$name(),$ref('feature_type_id','Cecha / uszlachetnienie','feature_types'),$code,['key'=>'value_type','label'=>'Typ wartości','type'=>'select','options'=>['text'=>'Tekst','number'=>'Liczba','boolean'=>'Tak / nie'],'default'=>'text'],['key'=>'unit','label'=>'Jednostka','type'=>'text','max'=>30],['key'=>'is_required','label'=>'Wymagane uzupełnienie','type'=>'boolean','default'=>0]]],
 ];
}
function activeReference(string $table,int $id): array {
 $row=reference($table,$id);
 if(isset($row['is_active']) && !$row['is_active']) fail('Wybrany wpis słownika jest nieaktywny.');
 return $row;
}
function dictionarySave(array $input): int {
 permit('users.manage');
 $category=text($input,'category',80);$definition=dictionaryDefinitions()[$category]??fail('Nieznana kategoria słownika.',404);
 $id=isset($input['id'])?integer($input,'id'):null;
 db()->beginTransaction();
 $old=$id?(one("SELECT * FROM $category WHERE id=? FOR UPDATE",[$id])??fail('Brak wpisu słownika.',404)):null;
 $data=[];
 foreach($definition['fields'] as $field){
  $key=$field['key'];$type=$field['type'];
  if($type==='permissions')continue;
  if($type==='boolean'){$v=$input[$key]??($field['default']??0);if(!in_array($v,[0,1,true,false,'0','1'],true))fail('Niepoprawna wartość: '.$field['label']);$data[$key]=(int)$v;}
  elseif($type==='reference'){$data[$key]=integer($input,$key);$ref=reference($field['source'],$data[$key]);if(isset($ref['is_active'])&&!$ref['is_active']&&(!$old||(int)$old[$key]!==$data[$key]))fail('Wybierz aktywny wpis: '.$field['label']);}
  elseif($type==='integer')$data[$key]=integer($input,$key,$field['min'],$field['max']);
  elseif($type==='select'){$v=$input[$key]??($field['default']??'');if(!isset($field['options'][$v]))fail('Niepoprawny wybór: '.$field['label']);$data[$key]=$v;}
  else {$data[$key]=text($input,$key,$field['max']??255,$field['required']??false);if($type==='code'){if(!preg_match('/^[a-z][a-z0-9_]*$/D',$data[$key]))fail('Kod: małe litery bez polskich znaków, cyfry i podkreślenia; pierwsza musi być litera.');if($old&&$old[$key]!==$data[$key])fail('Kod istniejącego wpisu jest stały.');}}
 }
 if($category==='order_statuses'&&$old){
  if(in_array($old['code'],['new','confirmed','production','ready','delivered','cancelled'],true)){
   if(!$data['is_active']||(int)$data['is_terminal']!==(int)$old['is_terminal'])fail('Status systemowy musi pozostać aktywny i zachować swój charakter.');
  }
 }
 if($category==='feature_types'&&$old&&$data['scope']!==$old['scope']){
  if($data['scope']==='component'&&one('SELECT id FROM item_features WHERE feature_type_id=? LIMIT 1',[$id]))fail('Cecha jest używana na produktach. Nie można zawęzić jej zakresu.');
  if($data['scope']==='item'&&one('SELECT id FROM component_features WHERE feature_type_id=? LIMIT 1',[$id]))fail('Cecha jest używana na częściach. Nie można zawęzić jej zakresu.');
 }
 if($category==='feature_parameter_definitions'&&$old){
  $used=one('SELECT item_feature_id FROM item_feature_values WHERE parameter_definition_id=? LIMIT 1',[$id])||one('SELECT component_feature_id FROM component_feature_values WHERE parameter_definition_id=? LIMIT 1',[$id]);
  if($used&&($data['value_type']!==$old['value_type']||(int)$data['feature_type_id']!==(int)$old['feature_type_id']))fail('Parametr jest używany. Nie można zmienić jego typu ani cechy.');
 }
 if($category==='roles'){
  $ids=$input['permission_ids']??[];if(!is_array($ids))fail('Niepoprawne uprawnienia.');$ids=array_values(array_unique(array_map(fn($v)=>integer(['id'=>$v],'id'),$ids)));
  foreach($ids as $pid)reference('permissions',$pid);
  $view=(int)one("SELECT id FROM permissions WHERE code='orders.view'")['id'];
  if(!in_array($view,$ids,true))fail('Rola musi umożliwiać podgląd zamówień, aby pracownik mógł korzystać z aplikacji.');
  if($old&&$old['code']==='admin'){
   $all=array_map('intval',array_column(rows('SELECT id FROM permissions'),'id'));sort($all);$compare=$ids;sort($compare);if($all!==$compare)fail('Administrator musi zachować wszystkie uprawnienia.');
  }
 }
 if($id){$assign=implode(',',array_map(fn($k)=>"`$k`=?",array_keys($data)));run("UPDATE $category SET $assign WHERE id=?",[...array_values($data),$id]);}
 else $id=insert($category,$data);
 if($category==='roles'){
  run('DELETE FROM role_permissions WHERE role_id=?',[$id]);
  foreach($ids as $pid)insert('role_permissions',['role_id'=>$id,'permission_id'=>$pid]);
 }
 db()->commit();return $id;
}
