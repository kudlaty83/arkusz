<?php
declare(strict_types=1);
function passwordValue(array $data,string $key='password'): string {
 $password=$data[$key]??null;
 if(!is_string($password)||str_contains($password,"\0")||preg_match_all('/./us',$password)<12||strlen($password)>72)fail('Hasło musi mieć co najmniej 12 znaków i nie przekraczać 72 bajtów UTF-8.');
 return $password;
}
function userDetails(int $id): array {
 $u=one('SELECT id,login,display_name,is_active,version FROM users WHERE id=?',[$id])??fail('Brak użytkownika.',404);
 $u['role_ids']=array_map('intval',array_column(rows('SELECT role_id FROM user_roles WHERE user_id=? ORDER BY role_id',[$id]),'role_id'));
 $u['work_center_ids']=array_map('intval',array_column(rows('SELECT work_center_id FROM user_work_centers WHERE user_id=? ORDER BY work_center_id',[$id]),'work_center_id'));
 return $u;
}
function userPage(array $input): array {
 permit('orders.view');[$page,$size]=listPage($input);$query=text($input,'q',120,false);$args=[];$where=[];
 $active=allowed('users.manage')?($input['active']??'all'):'1';if(!in_array((string)$active,['0','1','all'],true))fail('Nieprawidłowy filtr kont.');
 if($active!=='all'){$where[]='u.is_active=?';$args[]=(int)$active;}
 if($query!==''){$where[]="(u.display_name LIKE ? ESCAPE '!' OR u.login LIKE ? ESCAPE '!')";$args=[...$args,prefixSearch($query),prefixSearch($query)];}
 $filter=$where?' WHERE '.implode(' AND ',$where):'';
 db()->beginTransaction();$total=(int)one('SELECT COUNT(*) n FROM users u'.$filter,$args)['n'];$pages=max(1,(int)ceil($total/$size));$page=min($page,$pages);$offset=($page-1)*$size;
 $entries=rows("SELECT u.id,u.login,u.display_name,u.is_active,u.version,(SELECT GROUP_CONCAT(r.name ORDER BY r.id SEPARATOR ', ') FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id) roles FROM users u$filter ORDER BY u.display_name,u.id LIMIT $size OFFSET $offset",$args);
 db()->commit();return ['entries'=>$entries,'page'=>$page,'page_size'=>$size,'total'=>$total,'pages'=>$pages];
}
function userIds(array $data,string $key): array {
 $ids=$data[$key]??[];if(!is_array($ids)||count($ids)>1000)fail('Niepoprawna lista: '.$key);
 return array_values(array_unique(array_map(fn($v)=>integer(['id'=>$v],'id'),$ids)));
}
function renewCurrentSession(int $authVersion): void {
 session_regenerate_id(true);$_SESSION=['uid'=>(int)$_SESSION['uid'],'auth_version'=>$authVersion,'csrf'=>bin2hex(random_bytes(32))];
}
function saveUser(array $data,bool $editing,int $actor): int {
 permit('users.manage');$login=text($data,'login',100);$name=text($data,'display_name',200);$roles=userIds($data,'role_ids');$centers=userIds($data,'work_center_ids');
 if(!$roles)fail('Wybierz co najmniej jedną rolę.');
 $active=$data['is_active']??1;if(!in_array($active,[0,1,'0','1',false,true],true))fail('Nieprawidłowy status konta.');$active=(int)$active;
 $password=$editing?null:passwordValue($data);
 db()->beginTransaction();
 // Wspólna blokada serializuje zmiany administratorów, także dotyczące różnych kont.
 $adminRole=one("SELECT id FROM roles WHERE code='admin' FOR UPDATE")??fail('Brak roli administratora.');
 $id=$editing?integer($data,'id'):null;
 $old=$editing?(one('SELECT * FROM users WHERE id=? FOR UPDATE',[$id])??fail('Brak użytkownika.',404)):null;
 if($old&&(int)$old['version']!==integer($data,'version'))fail('Konto zmieniła inna osoba. Otwórz formularz ponownie.',409);
 if($id===$actor&&!$active)fail('Nie możesz wyłączyć własnego konta.');
 foreach($roles as $role)activeReference('roles',$role);
 $oldCenters=$old?array_map('intval',array_column(rows('SELECT work_center_id FROM user_work_centers WHERE user_id=?',[$id]),'work_center_id')):[];
 foreach($centers as $center)retainedReference('work_centers',$center,in_array($center,$oldCenters,true)?$center:null);
 if($old&&(!$active||!in_array((int)$adminRole['id'],$roles,true))&&one('SELECT 1 FROM user_roles WHERE user_id=? AND role_id=?',[$id,$adminRole['id']])&&!one('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id WHERE u.is_active=1 AND ur.role_id=? AND u.id<>? LIMIT 1',[$adminRole['id'],$id]))fail('Musi pozostać co najmniej jeden aktywny administrator.');
 $fields=['login'=>$login,'display_name'=>$name,'is_active'=>$active];$renew=false;
 if($old){
  $fields['version']=(int)$old['version']+1;
  if($old['login']!==$login||(int)$old['is_active']!==$active){$fields['auth_version']=(int)$old['auth_version']+1;$renew=$id===$actor;}
  updateRecord('users',$id,$fields);
 }else{$fields['password_hash']=password_hash($password,PASSWORD_DEFAULT);$id=insert('users',$fields);}
 run('DELETE FROM user_roles WHERE user_id=?',[$id]);foreach($roles as $role)insert('user_roles',['user_id'=>$id,'role_id'=>$role]);
 run('DELETE FROM user_work_centers WHERE user_id=?',[$id]);foreach($centers as $center)insert('user_work_centers',['user_id'=>$id,'work_center_id'=>$center]);
 db()->commit();if($renew)renewCurrentSession($fields['auth_version']);return $id;
}
function changePassword(array $data,int $actor,bool $reset): void {
 if($reset)permit('users.manage');
 $id=$reset?integer($data,'id'):$actor;if($reset&&$id===$actor)fail('Własne hasło zmień przez opcję „Zmień moje hasło”, podając obecne hasło.');
 $password=passwordValue($data);if(!is_string($data['password_confirmation']??null)||!hash_equals($password,$data['password_confirmation']))fail('Nowe hasła muszą być takie same.');
 db()->beginTransaction();$u=one('SELECT * FROM users WHERE id=? FOR UPDATE',[$id])??fail('Brak użytkownika.',404);
 if($reset){if((int)$u['version']!==integer($data,'version'))fail('Konto zmieniła inna osoba. Otwórz formularz ponownie.',409);}
 else{
  $attempts=array_values(array_filter($_SESSION['password_attempts']??[],fn($t)=>$t>time()-300));
  if(count($attempts)>=10)fail('Zbyt wiele prób zmiany hasła. Spróbuj za pięć minut.',429);
  if(!is_string($data['current_password']??null)||!password_verify($data['current_password'],$u['password_hash'])){$_SESSION['password_attempts']=[...$attempts,time()];fail('Obecne hasło jest nieprawidłowe.');}
 }
 $auth=(int)$u['auth_version']+1;
 run('UPDATE users SET password_hash=?,auth_version=?,version=version+1 WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$auth,$id]);
 db()->commit();if(!$reset)renewCurrentSession($auth);
}
