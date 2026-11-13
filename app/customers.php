<?php
declare(strict_types=1);
function listPage(array $input): array {
 $page=isset($input['page'])?integer($input,'page',1,1000000000):1;
 $size=isset($input['page_size'])?integer($input,'page_size',1,100):25;
 if(!in_array($size,[10,25,50,100],true))fail('Nieprawidłowy rozmiar strony.');
 return [$page,$size];
}
function prefixSearch(string $query): string {return strtr($query,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';}
function customerPage(array $input): array {
 permit('orders.view');[$page,$size]=listPage($input);$query=text($input,'q',120,false);$active=$input['active']??'1';
 if(!in_array((string)$active,['0','1','all'],true))fail('Nieprawidłowy filtr klientów.');
 $where=[];$args=[];
 if($active!=='all'){$where[]='is_active=?';$args[]=(int)$active;}
 if($query!==''){$where[]="(name LIKE ? ESCAPE '!' OR tax_id LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!' OR phone LIKE ? ESCAPE '!')";$args=[...$args,...array_fill(0,4,prefixSearch($query))];}
 $filter=$where?' WHERE '.implode(' AND ',$where):'';
 // Spójny licznik i strona także przy równoczesnym dodawaniu lub wyłączaniu klientów.
 db()->beginTransaction();
 $total=(int)one('SELECT COUNT(*) n FROM customers'.$filter,$args)['n'];$pages=max(1,(int)ceil($total/$size));$page=min($page,$pages);$offset=($page-1)*$size;
 $entries=rows("SELECT id,name,tax_id,email,phone,address_text,is_active,version FROM customers$filter ORDER BY name,id LIMIT $size OFFSET $offset",$args);
 db()->commit();return ['entries'=>$entries,'page'=>$page,'page_size'=>$size,'total'=>$total,'pages'=>$pages];
}
function saveCustomer(array $data,bool $editing): int {
 permit('orders.edit');
 $email=text($data,'email',254,false);if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))fail('Podaj poprawny adres e-mail.');
 $fields=['name'=>text($data,'name'),'tax_id'=>text($data,'tax_id',32,false),'email'=>$email,'phone'=>text($data,'phone',50,false),'address_text'=>text($data,'address_text',5000,false),'notes'=>text($data,'notes',10000,false)];
 $active=$data['is_active']??1;if(!in_array($active,[0,1,'0','1',false,true],true))fail('Nieprawidłowy status klienta.');$fields['is_active']=(int)$active;
 db()->beginTransaction();
 if($editing){
  $id=integer($data,'id');$old=one('SELECT * FROM customers WHERE id=? FOR UPDATE',[$id])??fail('Brak klienta.',404);
  if((int)$old['version']!==integer($data,'version'))fail('Klienta zmieniła inna osoba. Zamknij formularz i otwórz go ponownie.',409);
  $fields['version']=(int)$old['version']+1;updateRecord('customers',$id,$fields);
 }else $id=insert('customers',$fields);
 db()->commit();return $id;
}
