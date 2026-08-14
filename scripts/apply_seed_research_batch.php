<?php
declare(strict_types=1);

$dataFile=$argv[1]??'';
if($dataFile===''||!is_file($dataFile)){fwrite(STDERR,"ERROR: data file not found\n");exit(1);}
$data=json_decode((string)file_get_contents($dataFile),true);
$count=is_array($data)?count($data):0;
if(!is_array($data)||$count<1||$count>25){fwrite(STDERR,"ERROR: expected 1 to 25 seed records\n");exit(1);}
$numbers=array_map(static fn(array $r): string=>(string)($r['seed_number']??''),$data);
foreach($numbers as $number){if($number===''||!ctype_digit($number)||(int)$number<1){fwrite(STDERR,"ERROR: seed numbers must be positive integers\n");exit(1);}}
if(count(array_unique($numbers))!==count($numbers)){fwrite(STDERR,"ERROR: seed numbers must be unique\n");exit(1);}
$source=(string)file_get_contents(__DIR__.'/apply_seed_research.php');
$rangeNeedle="range(1, 25)";
if(substr_count($source,$rangeNeedle)!==1){fwrite(STDERR,"ERROR: updater template range changed unexpectedly\n");exit(1);}
$replacement='['.implode(',',array_map('intval',$numbers)).']';
$source=str_replace($rangeNeedle,$replacement,$source);
$batchNeedle="\$batches = [\n    '1' => array_slice(\$decoded, 0, 10),\n    '2' => array_slice(\$decoded, 10, 10),\n    '3' => array_slice(\$decoded, 20, 5),\n];";
if(substr_count($source,$batchNeedle)!==1){fwrite(STDERR,"ERROR: updater template batch block changed unexpectedly\n");exit(1);}
$source=str_replace($batchNeedle,"\$batches = ['1' => \$decoded];",$source);
$tmp=__DIR__.'/.apply_seed_research_batch_tmp.php';
file_put_contents($tmp,$source);
register_shutdown_function(static function()use($tmp):void{@unlink($tmp);});
$argv=[$tmp,$dataFile,'1'];
require $tmp;
