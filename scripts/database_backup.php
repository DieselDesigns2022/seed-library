#!/usr/bin/env php
<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';require __DIR__.'/../app/backup.php';
try{$root=resolve_backup_directory((string)config('app.backup_path','/var/backups/seed-library'));}catch(RuntimeException $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}chmod($root,0700);$json=json_encode(database_backup_payload(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$daily=$root.'/daily-'.gmdate('Y-m-d').'.json.gz';if(file_put_contents($daily,gzencode($json,9),LOCK_EX)===false){fwrite(STDERR,"Backup write failed.\n");exit(1);}chmod($daily,0600);if((int)gmdate('N')===7){$weekly=$root.'/weekly-'.gmdate('o-\WW').'.json.gz';copy($daily,$weekly);chmod($weekly,0600);}foreach(['daily-*.json.gz'=>7,'weekly-*.json.gz'=>4]as$pattern=>$keep){$files=glob($root.'/'.$pattern)?:[];rsort($files,SORT_STRING);foreach(array_slice($files,$keep)as$file)unlink($file);}echo "Database backup completed.\n";
