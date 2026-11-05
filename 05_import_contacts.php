<?php
declare(strict_types=1);
// Fast, set-based import. TEST DB ONLY. Requires an empty contacts table.
const DB_NAME = 'crm2_old_20261020';
const SOURCE = '/srv/projects/crm/export/client.jsonl';
const ENV = '/srv/projects/mf/crm/src/.env';
const STAGE = 'migration_contacts_stage_20261105';
const BATCH = 1000;
$apply = in_array('--apply', $argv, true);
function envFile(string $path): array {
    $out=[];
    foreach (file($path, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
        $line=trim($line);
        if ($line==='' || $line[0]==='#' || !str_contains($line,'=')) continue;
        [$k,$v]=explode('=',$line,2);
        $out[trim($k)]=trim(trim($v), "\"'");
    }
    return $out;
}
function val($v, int $max): ?string {
    if (!is_scalar($v)) return null;
    $v=trim((string)$v);
    return $v==='' ? null : mb_substr($v,0,$max,'UTF-8');
}
function stamp($v): string {
    if (!is_scalar($v) || $v==='') return '2020-01-01 00:00:00';
    $ts=is_numeric($v) ? (int)$v : strtotime((string)$v);
    return $ts && $ts>0 && $ts<4102444800 ? gmdate('Y-m-d H:i:s',$ts) : '2020-01-01 00:00:00';
}
if (!is_readable(SOURCE) || !is_readable(ENV)) exit("Source or .env not readable\n");
$env=envFile(ENV);
$pdo=new PDO('mysql:host='.($env['MYSQL_HOST']??'127.0.0.1').';port='.($env['MYSQL_PORT']??3306).';dbname='.DB_NAME.';charset=utf8mb4', $env['MYSQL_USER']??'', $env['MYSQL_PASSWORD']??'', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!==DB_NAME) exit("Wrong DB\n");
$clients=(int)$pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
$contacts=(int)$pdo->query('SELECT COUNT(*) FROM contacts')->fetchColumn();
echo "Database: ".DB_NAME."; clients=$clients; contacts=$contacts\n";
if (!$apply) {echo "DRY RUN. To import: php ".__FILE__." --apply\n";exit;}
if ($contacts!==0) exit("STOP: contacts table is not empty. No changes made.\n");
$linked=(int)$pdo->query('SELECT COUNT(*) FROM clients WHERE contact_pkid IS NOT NULL')->fetchColumn();
if ($linked!==0) exit("STOP: $linked clients already have contact_pkid. No changes made.\n");
$pdo->exec('CREATE TABLE IF NOT EXISTS '.STAGE.' (
 client_pkid BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 company_pkid BIGINT UNSIGNED NOT NULL,
 user_login VARCHAR(255) NULL,
 email VARCHAR(100) NULL,
 phone VARCHAR(30) NULL,
 display_name VARCHAR(255) NULL,
 gadd DATETIME NOT NULL,
 gchange DATETIME NOT NULL,
 uadd INT UNSIGNED NOT NULL,
 uchange INT UNSIGNED NOT NULL,
 KEY idx_login (company_pkid,user_login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$existing=(int)$pdo->query('SELECT COUNT(*) FROM '.STAGE)->fetchColumn();
if ($existing!==0) exit("STOP: staging table already contains $existing rows; inspect before rerunning.\n");
$fh=fopen(SOURCE,'rb');$rows=[];$seen=0;$staged=0;$start=time();
$flush=function() use (&$rows,&$staged,$pdo) {
 if (!$rows) return;
 foreach (array_chunk($rows,250) as $part) {
  $placeholders=implode(',',array_fill(0,count($part),'(?,?,?,?,?,?,?,?,?,?)'));
  $sql='INSERT IGNORE INTO '.STAGE.' (client_pkid,company_pkid,user_login,email,phone,display_name,gadd,gchange,uadd,uchange) VALUES '.$placeholders;
  $args=[];foreach($part as $r)foreach($r as $v)$args[]=$v;
  $pdo->prepare($sql)->execute($args);
 }
 $staged+=count($rows);$rows=[];
};
try {
 while (($line=fgets($fh))!==false) {
  ++$seen;$r=json_decode($line,true);
  if (!is_array($r) || !isset($r['pkid']) || !ctype_digit((string)$r['pkid'])) continue;
  $login=val($r['primary_login']??null,255);
  if ($login===null) {$alt=$r['login']??null;$login=val(is_array($alt)?($alt[0]??null):$alt,255);}
  $parts=[];foreach(['sname','fname','lname'] as $key){$v=val($r[$key]??null,255);if($v!==null)$parts[]=$v;}
  $name=val(implode(' ',$parts),255)??val($r['company_name']??null,255)??$login??'Без имени';
  $created=stamp($r['ugmtimeAdd']??($r['gmtimeAdd']??null));
  $changed=stamp($r['ugmtimeChange']??($r['gmtimeChange']??null));
  $uadd=(int)($r['ugmtimeAdd']??strtotime($created));$uchange=(int)($r['ugmtimeChange']??strtotime($changed));
  // Company comes from the already imported client row, not the raw source.
  $rows[]=[(string)$r['pkid'],0,$login,val($r['main_email']??null,100),val($r['main_phone']??null,30),$name,$created,$changed,max(0,min(4294967295,$uadd)),max(0,min(4294967295,$uchange))];
  if (count($rows)>=BATCH) $flush();
  if ($seen%100000===0) echo "Read $seen source rows (".(time()-$start)."s)\n";
 }
 $flush();fclose($fh);
 echo "Staged source IDs: $staged. Removing IDs not in clients and syncing company...\n";
 $pdo->exec('DELETE s FROM '.STAGE.' s LEFT JOIN clients c ON c.pkid=s.client_pkid WHERE c.pkid IS NULL');
 $pdo->exec('UPDATE '.STAGE.' s JOIN clients c ON c.pkid=s.client_pkid SET s.company_pkid=c.company_pkid');
 $total=(int)$pdo->query('SELECT COUNT(*) FROM '.STAGE)->fetchColumn();
 echo "Valid staged clients: $total. Creating contacts in two set-based INSERTs...\n";
 // Stable unique contact IDs: for a login group, smallest client ID; for no login, client ID.
 $pdo->exec("INSERT INTO contacts (pkid,company_pkid,user_pkid,user_login,email,phone,displayName,is_deleted,gmtimeAdd,gmtimeChange,ugmtimeAdd,ugmtimeChange)
 SELECT s.client_pkid,s.company_pkid,NULL,NULL,s.email,s.phone,s.display_name,0,s.gadd,s.gchange,s.uadd,s.uchange
 FROM ".STAGE." s WHERE s.user_login IS NULL");
 $pdo->exec("INSERT INTO contacts (pkid,company_pkid,user_pkid,user_login,email,phone,displayName,is_deleted,gmtimeAdd,gmtimeChange,ugmtimeAdd,ugmtimeChange)
 SELECT s.client_pkid,s.company_pkid,NULL,s.user_login,s.email,s.phone,s.display_name,0,s.gadd,s.gchange,s.uadd,s.uchange
 FROM ".STAGE." s INNER JOIN
 (SELECT company_pkid,user_login,MIN(client_pkid) AS first_id FROM ".STAGE." WHERE user_login IS NOT NULL GROUP BY company_pkid,user_login) g
 ON g.first_id=s.client_pkid");
 echo "Contacts created: ".$pdo->query('SELECT COUNT(*) FROM contacts')->fetchColumn().". Linking clients...\n";
 // For matching groups, contact PKID is the smallest client PKID.
 $pdo->exec('UPDATE clients c JOIN '.STAGE.' s ON s.client_pkid=c.pkid SET c.contact_pkid=s.client_pkid,c.user_login=s.user_login,c.user_pkid=NULL WHERE s.user_login IS NULL');
 $pdo->exec('UPDATE clients c JOIN '.STAGE.' s ON s.client_pkid=c.pkid JOIN
 (SELECT company_pkid,user_login,MIN(client_pkid) AS contact_id FROM '.STAGE.' WHERE user_login IS NOT NULL GROUP BY company_pkid,user_login) g
 ON g.company_pkid=s.company_pkid AND g.user_login=s.user_login
 SET c.contact_pkid=g.contact_id,c.user_login=s.user_login,c.user_pkid=NULL WHERE s.user_login IS NOT NULL');
 echo "Done in ".(time()-$start)."s; contacts=".$pdo->query('SELECT COUNT(*) FROM contacts')->fetchColumn()."; linked clients=".$pdo->query('SELECT COUNT(*) FROM clients WHERE contact_pkid IS NOT NULL')->fetchColumn()."\n";
} catch(Throwable $e) {if(is_resource($fh))fclose($fh);fwrite(STDERR,"ERROR: ".$e->getMessage()."\nStaging retained for investigation; do NOT rerun or clear tables blindly.\n");exit(1);}
