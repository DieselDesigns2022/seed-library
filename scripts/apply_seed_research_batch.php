<?php
declare(strict_types=1);

$dataFile=$argv[1]??'';
if($dataFile===''||!is_file($dataFile)){fwrite(STDERR,"ERROR: data file not found\n");exit(1);}
$data=json_decode((string)file_get_contents($dataFile),true);
if(!is_array($data)||count($data)!==10){fwrite(STDERR,"ERROR: expected exactly 10 seed records\n");exit(1);}
$numbers=array_map(static fn(array $r): int=>(int)($r['seed_number']??0),$data);
$start=$numbers[0];$end=$numbers[9];
if($numbers!==range($start,$end)){fwrite(STDERR,"ERROR: seed numbers must be 10 consecutive values\n");exit(1);}
$source=(string)file_get_contents(__DIR__.'/apply_seed_research.php');
$needle="range(1, 25)";
if(substr_count($source,$needle)!==1){fwrite(STDERR,"ERROR: updater template changed unexpectedly\n");exit(1);}
$source=str_replace($needle,"range($start, $end)",$source);
$tmp=__DIR__.'/.apply_seed_research_batch_tmp.php';
file_put_contents($tmp,$source);
register_shutdown_function(static function()use($tmp):void{@unlink($tmp);});
$argv=[$tmp,$dataFile,'1'];
require $tmp;
