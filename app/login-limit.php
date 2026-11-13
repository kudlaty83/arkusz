<?php
declare(strict_types=1);
function loginClientAddress(): string {
 $peer=$_SERVER['REMOTE_ADDR']??'';$pack=@inet_pton($peer);if($pack===false)return 'unknown';$peer=inet_ntop($pack);
 $trusted=[];foreach(explode(',',configValue('TRUSTED_PROXY_IPS','')) as $value){$ip=@inet_pton(trim($value));if($ip!==false)$trusted[]=inet_ntop($ip);}
 if(!in_array($peer,$trusted,true))return $peer;
 $header=$_SERVER['HTTP_X_FORWARDED_FOR']??'';if(strlen($header)>4096)return $peer;
 $chain=explode(',',$header);if(count($chain)>32)return $peer;$chain[]=$peer;
 foreach(array_reverse($chain) as $value){$ip=@inet_pton(trim($value));if($ip===false)return $peer;$ip=inet_ntop($ip);if(!in_array($ip,$trusted,true))return $ip;}
 return $peer;
}
function reserveLoginAttempt(string $login,?array $user): string {
 $account=$user?'id:'.$user['id']:'name:'.strtolower($login);$source=loginClientAddress();
 $spec=[['pair',$account.'|'.$source,10,300],['account',$account,60,60],['source',$source,500,300],['global','all',1000,60]];$buckets=[];$pair='';
 foreach($spec as [$kind,$value,$limit,$window]){$key=hash('sha256',$kind.':'.$value);$buckets[$key]=[$kind,$limit,$window];if($kind==='pair')$pair=$key;}ksort($buckets);
 run('DELETE FROM login_attempt_limits WHERE expires_at<UTC_TIMESTAMP() LIMIT 100');
 for($attempt=0;;$attempt++){try {db()->beginTransaction();$now=(int)one('SELECT UNIX_TIMESTAMP(UTC_TIMESTAMP()) n')['n'];$retry=0;
 foreach($buckets as $key=>[$kind,$limit,$window]){
  run('INSERT INTO login_attempt_limits(bucket_key,bucket_kind,window_started,attempts,expires_at) VALUES(?,?,?,0,FROM_UNIXTIME(?)) ON DUPLICATE KEY UPDATE bucket_key=VALUES(bucket_key)',[$key,$kind,$now,$now+$window]);
  $row=one('SELECT * FROM login_attempt_limits WHERE bucket_key=? FOR UPDATE',[$key]);$start=(int)$row['window_started'];$count=(int)$row['attempts'];
  if($start+$window<=$now){$start=$now;$count=0;}
  if($count>=$limit)$retry=max($retry,$start+$window-$now);
  else $count++;
  run('UPDATE login_attempt_limits SET window_started=?,attempts=?,expires_at=FROM_UNIXTIME(?) WHERE bucket_key=?',[$start,$count,$start+$window,$key]);
 }
 db()->commit();break;
 }catch(PDOException $error){if(db()->inTransaction())db()->rollBack();if(!in_array((int)($error->errorInfo[1]??0),[1205,1213],true)||$attempt>=4)throw $error;usleep(random_int(1000,15000));}
 }
 if($retry>0){header('Retry-After: '.$retry);fail('Zbyt wiele prób logowania. Spróbuj ponownie później.',429);}return $pair;
}
