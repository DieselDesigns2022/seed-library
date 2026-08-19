<?php
declare(strict_types=1);
function backup_tables(): array{return ['users','categories','plant_families','uses','statuses','settings','seeds','seed_locations','seed_uses','companion_relationships','seed_history','garden_plantings'];}
function legacy_backup_tables(): array{return array_values(array_diff(backup_tables(),['garden_plantings']));}
function resolve_backup_directory(string $configured): string
{
    $configured=rtrim(trim($configured),DIRECTORY_SEPARATOR);
    if($configured===''||(!is_dir($configured)&&!mkdir($configured,0700,true)))throw new RuntimeException('The backup directory is unavailable.');
    $resolved=realpath($configured);$public=realpath(BASE_PATH.'/public');
    if($resolved===false||$public===false)throw new RuntimeException('The backup directory could not be resolved.');
    $resolved=rtrim($resolved,DIRECTORY_SEPARATOR);$public=rtrim($public,DIRECTORY_SEPARATOR);
    if($resolved===$public||str_starts_with($resolved,$public.DIRECTORY_SEPARATOR))throw new RuntimeException('The backup directory must be outside public/.');
    return $resolved;
}
function database_backup_payload(): array {$data=[];foreach(backup_tables()as$table)$data[$table]=db()->query("SELECT * FROM `$table`")->fetchAll();return ['format'=>'seed-library-backup','version'=>2,'created_at'=>gmdate(DATE_ATOM),'tables'=>$data];}
function backup_has_valid_owner(array $users): bool
{
    foreach($users as$user){if(!is_array($user)||empty($user['is_owner'])||!ctype_digit((string)($user['id']??''))||(int)$user['id']<1||!filter_var($user['email']??'',FILTER_VALIDATE_EMAIL))continue;$hash=(string)($user['password_hash']??'');$info=password_get_info($hash);if($hash!==''&&($info['algoName']??'unknown')!=='unknown')return true;}return false;
}
function validate_backup_payload(mixed $data): array
{
    if(!is_array($data)||($data['format']??'')!=='seed-library-backup'||!is_array($data['tables']??null))throw new RuntimeException('This is not a supported Seed Library database backup.');
    $version=$data['version']??null;if($version!==1&&$version!==2)throw new RuntimeException('This backup version is not supported.');
    $required=$version===1?legacy_backup_tables():backup_tables();$keys=array_keys($data['tables']);
    foreach($required as$t)if(!array_key_exists($t,$data['tables'])||!is_array($data['tables'][$t]))throw new RuntimeException('The backup is incomplete.');
    if(array_diff($keys,$required))throw new RuntimeException('The backup contains unsupported tables.');
    if(!backup_has_valid_owner($data['tables']['users']))throw new RuntimeException('The backup has no valid owner account.');
    if($version===1)$data['tables']['garden_plantings']=[];
    return$data;
}
function download_database_backup(): never {$json=json_encode(database_backup_payload(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);header('Content-Type: application/json');header('Content-Disposition: attachment; filename="seed-library-database-'.gmdate('Y-m-d-His').'.json"');header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store, private');echo$json;exit;}
function restore_database_backup(array $backup): void {$pdo=db();$tables=backup_tables();try{$pdo->beginTransaction();$pdo->exec('SET FOREIGN_KEY_CHECKS=0');foreach(array_reverse($tables)as$t)$pdo->exec("DELETE FROM `$t`");foreach($tables as$t){foreach($backup['tables'][$t]as$row){if(!is_array($row)||!$row)continue;$columns=array_keys($row);foreach($columns as$c)if(!preg_match('/^[a-z_]+$/',$c))throw new RuntimeException('Invalid backup column.');$sql="INSERT INTO `$t` (`".implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')';$pdo->prepare($sql)->execute(array_values($row));}}$pdo->exec('SET FOREIGN_KEY_CHECKS=1');$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();try{$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}catch(Throwable){}throw$e;}}
function backup_page(): void {require_owner();if(is_post()){verify_csrf();$action=(string)($_POST['action']??'');if($action==='download')download_database_backup();if($action==='restore'){if(($_POST['confirmation']??'')!=='RESTORE DATABASE'){flash('danger','Type RESTORE DATABASE exactly to confirm the destructive restore.');redirect('backup');}$u=$_FILES['backup_file']??null;if(!$u||($u['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($u['size']??0)>100*1024*1024){flash('danger','Choose a valid backup file no larger than 100 MB.');redirect('backup');}try{$raw=file_get_contents($u['tmp_name']);if($raw===false)throw new RuntimeException();$data=validate_backup_payload(json_decode($raw,true,512,JSON_THROW_ON_ERROR));restore_database_backup($data);$_SESSION=[];session_regenerate_id(true);flash('success','Database restore completed. Sign in with an owner account from the restored database.');redirect('login');}catch(Throwable $e){error_log((string)$e);flash('danger','Restore failed safely. The file was invalid or could not be restored; database and server details were not exposed.');}redirect('backup');}}render('Database Backup & Restore',function(){?><h1>Database Backup &amp; Restore</h1><div class="alert alert-warning"><strong>Owner-only destructive tools.</strong> A database backup includes accounts, settings, relationships, history, and all seeds. It is separate from seed exports and must be stored securely.</div><div class="row g-3"><div class="col-lg-5"><form method="post" class="card card-body"><?=csrf_field()?><input type="hidden" name="action" value="download"><h2 class="h4">Full database backup</h2><p>The download is generated directly and is never placed in the public web root.</p><button class="btn btn-success">Download Full Database Backup</button></form></div><div class="col-lg-7"><form method="post" enctype="multipart/form-data" class="card card-body" data-confirm="This replaces the entire database. Continue?"><?=csrf_field()?><input type="hidden" name="action" value="restore"><h2 class="h4">Restore full database</h2><p class="text-danger">This replaces every application table. The backup must contain a valid owner account. A successful restore ends this session and requires a fresh login.</p><input class="form-control mb-3" type="file" name="backup_file" accept=".json,application/json" required><label class="form-label">Type RESTORE DATABASE</label><input class="form-control mb-3" name="confirmation" required autocomplete="off"><button class="btn btn-danger">Validate &amp; Restore</button></form></div></div><?php });}
