<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/calendar.php';
$index=file_get_contents($root.'/public/index.php');

$dedicated=[
    'planting_method'=>'Start Indoors',
    'indoor_start_month'=>2,'indoor_start_day'=>15,'indoor_end_month'=>3,'indoor_end_day'=>16,
    'direct_sow_start_month'=>11,'direct_sow_start_day'=>null,'direct_sow_end_month'=>2,'direct_sow_end_day'=>null,
    'transplant_start_month'=>5,'transplant_start_day'=>16,'transplant_end_month'=>6,'transplant_end_day'=>15,
];
$timeline=calendar_seed_timeline($dedicated);
$fallback=['planting_method'=>'Direct Sow','plantable_months'=>'4,6'];
$harvestOnlyJuly=['days_to_maturity_min'=>91,'days_to_maturity_max'=>91,'direct_sow_start_month'=>4,'direct_sow_start_day'=>1];
$checks=[
    'visual calendar is the default and table view remains selectable'=>str_contains($index,"??'visual')==='table'?'table':'visual'")&&str_contains($index,'Visual Calendar')&&str_contains($index,'Table View'),
    'timeline renders exactly 24 half-month segments'=>str_contains($index,'for($segment=1;$segment<=24;$segment++)')&&str_contains($index,'Early')&&str_contains($index,'Late'),
    'dedicated activity ranges map exact early and late halves'=>$timeline['start_indoors']===[3,4,5,6]&&$timeline['transplant']===[10,11],
    'cross-year month-only range includes both halves of each occupied month'=>$timeline['direct_sow']===[1,2,3,4,21,22,23,24],
    'plantable months fallback is restricted to a supported planting method'=>calendar_activity_segments($fallback,'direct_sow')===[7,8,11,12]&&calendar_activity_segments($fallback,'transplant')===[],
    'harvest is not fabricated without exact outdoor date'=>calendar_harvest_segments(['days_to_maturity_min'=>60,'days_to_maturity_max'=>70,'direct_sow_start_month'=>4])===[],
    'harvest uses an exact outdoor date and stored maturity range'=>calendar_harvest_segments(['days_to_maturity_min'=>30,'days_to_maturity_max'=>45,'direct_sow_start_month'=>4,'direct_sow_start_day'=>1])===[9,10],
    'harvest-only month does not satisfy selected-month planting semantics'=>in_array(13,calendar_harvest_segments($harvestOnlyJuly),true)&&!calendar_seed_matches_planting_month($harvestOnlyJuly,7),
    'general and each planting activity can satisfy selected-month semantics'=>calendar_seed_matches_planting_month(['plantable_months'=>'7'],7)&&calendar_seed_matches_planting_month(['indoor_start_month'=>7,'indoor_end_month'=>7],7)&&calendar_seed_matches_planting_month(['direct_sow_start_month'=>7,'direct_sow_end_month'=>7],7)&&calendar_seed_matches_planting_month(['transplant_start_month'=>7,'transplant_end_month'=>7],7),
    'existing group filtering stays centralized'=>calendar_method_month_matches($dedicated,'direct_sow',1)&&!calendar_method_month_matches($dedicated,'direct_sow',6)&&array_keys(calendar_group_rules())===['direct_sow','start_indoors','transplant','fall_crop','flowers','herbs','medicinal'],
    'compact table notes remain disclosure-only'=>str_contains($index,'<details><summary')&&str_contains($index,'calendar-notes')&&!str_contains($index,'calendar_seed_timeline($seed)[\'notes\']'),
];
$failed=0;
foreach($checks as $name=>$ok){echo($ok?'PASS':'FAIL').": $name\n";if(!$ok)$failed++;}
exit($failed?1:0);
