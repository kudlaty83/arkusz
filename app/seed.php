<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
if(one('SELECT id FROM users LIMIT 1')) { echo "Baza już zainicjalizowana.\n";return; }
$password=configValue('ADMIN_PASSWORD');if(!$password||strlen($password)<12)throw new RuntimeException('Ustaw ADMIN_PASSWORD min. 12 znaków.');
db()->beginTransaction();
foreach(['orders.view'=>'Podgląd zamówień','orders.edit'=>'Obsługa zamówień','tasks.manage'=>'Planowanie i przydzielanie pracy','tasks.report'=>'Raportowanie pracy','users.manage'=>'Zarządzanie pracownikami'] as $code=>$name)insert('permissions',compact('code','name'));
$roles=['admin'=>'Administrator','bok'=>'BOK','graphic'=>'Grafik','printer'=>'Drukarz','binder'=>'Introligator','manager'=>'Kierownik produkcji'];
foreach($roles as $code=>$name){ $rid=insert('roles',compact('code','name'));$perms=match($code){'admin'=>['orders.view','orders.edit','tasks.manage','tasks.report','users.manage'],'bok','manager'=>['orders.view','orders.edit','tasks.manage','tasks.report'],default=>['orders.view','tasks.report']};foreach($perms as $p)insert('role_permissions',['role_id'=>$rid,'permission_id'=>one('SELECT id FROM permissions WHERE code=?',[$p])['id']]); }
$admin=insert('users',['login'=>'admin','display_name'=>'Administrator','password_hash'=>password_hash($password,PASSWORD_DEFAULT)]);insert('user_roles',['user_id'=>$admin,'role_id'=>1]);
foreach(['prepress'=>'Studio graficzne','offset'=>'Druk offsetowy','digital'=>'Druk cyfrowy','bindery'=>'Introligatornia','dispatch'=>'Pakowanie i wydanie'] as $code=>$name){$cid=insert('work_centers',compact('code','name'));insert('user_work_centers',['user_id'=>$admin,'work_center_id'=>$cid]);}
foreach(['business_card'=>'Wizytówka','stationery'=>'Akcydens','book'=>'Książka','flyer'=>'Ulotka','poster'=>'Plakat','folder'=>'Folder','other'=>'Inne'] as $code=>$name)insert('product_types',compact('code','name'));
foreach(['Offsetowy','Kreda matowa','Kreda błyszcząca','Ozdobny','Karton'] as $name)insert('paper_types',compact('name'));
foreach([[1,80,'Offset 80'],[1,90,'Offset 90'],[1,120,'Offset 120'],[2,130,'Kreda mat 130'],[2,170,'Kreda mat 170'],[2,250,'Kreda mat 250'],[2,350,'Kreda mat 350'],[3,135,'Kreda błysk 135'],[3,170,'Kreda błysk 170'],[4,300,'Papier ozdobny 300'],[5,300,'Karton 300']] as [$type,$gsm,$name])insert('paper_variants',['paper_type_id'=>$type,'name'=>$name,'grammage_gsm'=>$gsm]);
foreach(['new'=>'Nowe','confirmed'=>'Zatwierdzone','production'=>'W produkcji','ready'=>'Gotowe','delivered'=>'Wydane','cancelled'=>'Anulowane'] as $code=>$name)insert('order_statuses',['code'=>$code,'name'=>$name,'is_terminal'=>in_array($code,['delivered','cancelled'])?1:0]);
foreach(['design'=>'Projekt graficzny','preflight'=>'Przygotowanie plików','approval'=>'Akceptacja projektu','print'=>'Druk','cut'=>'Cięcie','fold'=>'Falcowanie','bind'=>'Oprawa','laminate'=>'Foliowanie','varnish'=>'Lakierowanie','emboss'=>'Tłoczenie','diecut'=>'Wykrawanie','crease'=>'Bigowanie','check'=>'Kontrola jakości','pack'=>'Pakowanie'] as $code=>$name)insert('operation_types',compact('code','name'));
foreach(['emboss'=>'Przetłoczenie','diecut'=>'Wykrojenie / wycięcia','varnish'=>'Lakierowanie','laminate'=>'Foliowanie','perforation'=>'Perforacja','crease'=>'Bigowanie','corners'=>'Zaokrąglone narożniki','binding'=>'Oprawa'] as $code=>$name){$fid=insert('feature_types',compact('code','name'));foreach([['kind','Rodzaj / wariant'],['side','Strona'],['area','Obszar / położenie'],['tool','Wykrojnik / matryca / plik maski']] as [$pc,$pn])insert('feature_parameter_definitions',['feature_type_id'=>$fid,'code'=>$pc,'name'=>$pn,'is_required'=>$pc==='kind'?1:0]);}
if(configValue('SEED_DEMO','0')!=='1'){db()->commit();echo "Utworzono słowniki i konto administratora.\n";return;}
// Jeden jawnie oznaczony przykład ułatwia poznanie aplikacji; brak fikcyjnych pracowników.
$customer=insert('customers',['name'=>'Przykładowy klient — Studio Papier','email'=>'studio@example.test','phone'=>'000 000 000','notes'=>'Dane demonstracyjne.']);
$status=one("SELECT id FROM order_statuses WHERE code='new'")['id'];
$order=insert('orders',['order_number'=>'DEMO/001','customer_id'=>$customer,'customer_name'=>'Przykładowy klient — Studio Papier','title'=>'Wizytówki z lakierem wybiórczym','status_id'=>$status,'priority'=>'normal','due_at'=>gmdate('Y-m-d H:i:s',time()+86400*5),'created_by'=>$admin,'internal_notes'=>'Przykładowe zamówienie. Możesz utworzyć własne przyciskiem „Nowe zamówienie”.']);
insert('order_status_history',['order_id'=>$order,'new_status_id'=>$status,'changed_by'=>$admin,'reason'=>'Dane demonstracyjne']);
$item=insert('order_items',['order_id'=>$order,'line_number'=>1,'product_type_id'=>1,'product_type_name'=>'Wizytówka','name'=>'Wizytówki 90 × 50','quantity'=>500,'finished_width_mm'=>90,'finished_height_mm'=>50,'agreed_total_net'=>240,'tax_rate'=>23]);
$component=insert('item_components',['order_item_id'=>$item,'name'=>'Karta','page_count'=>2,'paper_variant_id'=>7,'paper_description'=>'Kreda mat 350','grammage_gsm'=>350,'print_technique'=>'digital','digital_front_mode'=>'full_color','digital_back_mode'=>'full_color']);
$feature=insert('component_features',['component_id'=>$component,'feature_type_id'=>3,'notes'=>'Logo na awersie']);
$pd=one("SELECT id FROM feature_parameter_definitions WHERE feature_type_id=3 AND code='kind'")['id'];insert('component_feature_values',['component_feature_id'=>$feature,'parameter_definition_id'=>$pd,'value_text'=>'UV wybiórczy']);
$prev=null;foreach([[2,1,'Przygotuj plik do druku i maskę lakieru.'],[4,3,'Druk dwustronny, pełny kolor.'],[9,4,'Lakier wybiórczy na logo.'],[5,4,'Format netto 90 × 50 mm.']] as $ix=>[$op,$center,$instructions]){
 $tid=insert('production_tasks',['order_item_id'=>$item,'component_id'=>$component,'operation_type_id'=>$op,'operation_name'=>referenceName($op),'sequence_number'=>($ix+1)*10,'work_center_id'=>$center,'planned_quantity'=>500,'quantity_mode'=>'per_copy','quantity_base_quantity'=>1,'quantity_base_copies'=>1,'quantity_review_required'=>0,'quantity_unit'=>'szt.','due_at'=>gmdate('Y-m-d H:i:s',time()+86400*5),'instructions'=>$instructions]);
 insert('task_assignments',['task_id'=>$tid,'user_id'=>$admin,'assigned_by'=>$admin]);if($prev)insert('task_dependencies',['task_id'=>$tid,'depends_on_task_id'=>$prev]);if($op===9)insert('task_component_features',['task_id'=>$tid,'component_feature_id'=>$feature]);
 insert('task_status_history',['task_id'=>$tid,'new_status'=>'pending','event_type'=>'created','changed_by'=>$admin,'reason'=>'Dane demonstracyjne']);$prev=$tid;
}
db()->commit();echo "Utworzono słowniki, konto administratora i przykład.\n";
function referenceName(int $id): string {return one('SELECT name FROM operation_types WHERE id=?',[$id])['name'];}
