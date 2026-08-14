<?php
declare(strict_types=1);

$dataFile=$argv[1]??'';
if($dataFile===''||!is_file($dataFile)){fwrite(STDERR,"ERROR: data file not found\n");exit(1);}
$data=json_decode((string)file_get_contents($dataFile),true);
if(!is_array($data)||count($data)!==10){fwrite(STDERR,"ERROR: expected exactly 10 seed records\n");exit(1);}
$numbers=array_map(static fn(array $r): string=>(string)($r['seed_number']??''),$data);
foreach($numbers as $number){if($number===''||!ctype_digit($number)||(int)$number<1){fwrite(STDERR,"ERROR: seed numbers must be positive integers\n");exit(1);}}
if(count(array_unique($numbers))!==10){fwrite(STDERR,"ERROR: seed numbers must be unique\n");exit(1);}
$source=(string)file_get_contents(__DIR__.'/apply_seed_research.php');
$needle="range(1, 25)";
if(substr_count($source,$needle)!==1){fwrite(STDERR,"ERROR: updater template changed unexpectedly\n");exit(1);}
$replacement='['.implode(',',array_map('intval',$numbers)).']';
$source=str_replace($needle,$replacement,$source);
$tmp=__DIR__.'/.apply_seed_research_batch_tmp.php';
file_put_contents($tmp,$source);
register_shutdown_function(static function()use($tmp):void{@unlink($tmp);});
$argv=[$tmp,$dataFile,'1'];
require $tmp;
