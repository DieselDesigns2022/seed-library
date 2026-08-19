<?php
declare(strict_types=1);
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';require_once $root.'/app/garden.php';
$schema=file_get_contents($root.'/database/schema.sql');$index=file_get_contents($root.'/public/index.php');$view=file_get_contents($root.'/app/view.php');$backup=file_get_contents($root.'/app/backup.php');
$direct=['planted_date'=>'2026-04-01','planting_method'=>'Direct Sown','days_to_germination_min'=>5,'days_to_germination_max'=>10,'days_to_maturity_min'=>60,'days_to_maturity_max'=>70,'maturity_qualifier'=>'days from sowing'];
$transplant=['planted_date'=>'2026-02-01','planting_method'=>'Started Indoors','actual_transplant_date'=>'2026-05-10','days_to_maturity'=>80,'maturity_qualifier'=>'days from transplant'];
$window=['planting_method'=>'Started Indoors','planted_date'=>'2026-02-01','transplant_start_month'=>5,'transplant_start_day'=>1,'transplant_end_month'=>5,'transplant_end_day'=>31];
$actualHarvest=$direct+['actual_harvest_date'=>'2026-07-01'];
$checks=[
 'schema permits unlimited independent plantings per seed'=>preg_match('/CREATE TABLE garden_plantings \((.*?)\) ENGINE/s',$schema,$gardenTable)===1&&!str_contains($gardenTable[1],'seed_id INT UNSIGNED NOT NULL UNIQUE'),
 'planting history protects its referenced seed'=>str_contains($schema,'fk_garden_plantings_seed')&&str_contains($schema,'ON DELETE RESTRICT'),
 'full database backups include planting history'=>str_contains($backup,"'garden_plantings'"),
 'invalid garden values are rejected'=>count(garden_validate(['seed_id'=>'','planted_date'=>'bad','planting_method'=>'Guess','quantity'=>0,'location'=>'','status'=>'Nope']))>=6,
 'failed create retains every submitted string'=>garden_failed_form_state([],['seed_id'=>'999','planted_date'=>'invalid','planting_method'=>'Other','quantity'=>'zero','location'=>'Bed Z','status'=>'Growing','actual_transplant_date'=>'bad-t','actual_harvest_date'=>'bad-h','notes'=>'keep me'])===['seed_id'=>'999','planted_date'=>'invalid','planting_method'=>'Other','quantity'=>'zero','location'=>'Bed Z','notes'=>'keep me','actual_transplant_date'=>'bad-t','actual_harvest_date'=>'bad-h','status'=>'Growing']&&str_contains($index,'if(!$id&&!is_post())'),
 'germination range uses actual planted date'=>garden_expected_germination($direct)===['2026-04-06','2026-04-11'],
 'germination applies only to actually sown methods'=>garden_expected_germination(array_merge($direct,['planting_method'=>'Started Indoors']))!==null&&garden_expected_germination(array_merge($direct,['planting_method'=>'Winter Sown']))!==null&&garden_expected_germination(array_merge($direct,['planting_method'=>'Transplanted']))===null&&garden_expected_germination(array_merge($direct,['planting_method'=>'Other']))===null,
 'supported direct harvest uses sowing basis'=>garden_expected_harvest($direct)===['2026-05-31','2026-06-10'],
 'supported transplant harvest uses actual transplant basis'=>garden_expected_harvest($transplant)===['2026-07-29','2026-07-29'],
 'transplanted planting date is a defensible transplant maturity basis'=>garden_expected_harvest(['planted_date'=>'2026-05-10','planting_method'=>'Transplanted','days_to_maturity'=>80,'maturity_qualifier'=>'days from transplant'])===['2026-07-29','2026-07-29'],
 'non-transplanted method without actual transplant has no transplant-basis harvest'=>garden_expected_harvest(['planted_date'=>'2026-05-10','planting_method'=>'Started Indoors','days_to_maturity'=>80,'maturity_qualifier'=>'days from transplant'])===null,
 'unqualified direct-sown maturity has a defensible planting basis'=>garden_expected_harvest($direct+['maturity_qualifier'=>''])===['2026-05-31','2026-06-10'],
 'unknown maturity qualifier is unsupported'=>garden_expected_harvest(array_merge($direct,['maturity_qualifier'=>'from an unspecified event']))===null,
 'actual harvest suppresses expected harvest'=>garden_expected_harvest($actualHarvest)===null,
 'actual transplant suppresses expected transplant'=>garden_expected_transplant($transplant)===null,
 'early indoor start uses same-year transplant window'=>garden_expected_transplant($window)===['2026-05-01','2026-05-31'],
 'late indoor start rolls spring window to following year'=>garden_expected_transplant(array_merge($window,['planted_date'=>'2026-11-01']))===['2027-05-01','2027-05-31'],
 'explicit transplant window supports cross-year dates'=>garden_expected_transplant(['planting_method'=>'Winter Sown','planted_date'=>'2026-11-01','transplant_start_month'=>12,'transplant_start_day'=>15,'transplant_end_month'=>1,'transplant_end_day'=>15])===['2026-12-15','2027-01-15'],
 'weeks-after-frost rolls forward rather than preceding planting'=>garden_expected_transplant(['planting_method'=>'Started Indoors','planted_date'=>'2026-11-01','transplant_weeks_after_frost'=>2],'05-05')===['2027-05-19','2027-05-19'],
 'transplant timing applies only to methods that need transplanting'=>garden_expected_transplant(array_merge($window,['planting_method'=>'Direct Sown']))===null&&garden_expected_transplant(array_merge($window,['planting_method'=>'Transplanted']))===null&&garden_expected_transplant(array_merge($window,['planting_method'=>'Other']))===null,
 'missing transplant evidence is unsupported'=>garden_expected_transplant(['planting_method'=>'Started Indoors','planted_date'=>'2026-02-01'])===null,
 'actual-date chronology is validated'=>in_array('Actual transplant date cannot be before planted date.',garden_validate(['seed_id'=>'','planted_date'=>'2026-05-01','planting_method'=>'Direct Sown','quantity'=>1,'location'=>'Bed','status'=>'Growing','actual_transplant_date'=>'2026-04-30']),true)&&in_array('Actual harvest date cannot be before planted date.',garden_validate(['seed_id'=>'','planted_date'=>'2026-05-01','planting_method'=>'Direct Sown','quantity'=>1,'location'=>'Bed','status'=>'Growing','actual_harvest_date'=>'2026-04-30']),true)&&in_array('Actual harvest date cannot be before actual transplant date.',garden_validate(['seed_id'=>'','planted_date'=>'2026-05-01','planting_method'=>'Direct Sown','quantity'=>1,'location'=>'Bed','status'=>'Growing','actual_transplant_date'=>'2026-05-10','actual_harvest_date'=>'2026-05-09']),true),
 'new write routes use POST and CSRF'=>str_contains($index,'function garden_status_action')&&str_contains($index,'function winter_research_action')&&substr_count($index,'require_post();verify_csrf()')>=2,
 'authenticated navigation exposes both areas'=>str_contains($view,'My Garden')&&str_contains($view,'Winter Sowing'),
 'duplicate preserves research without exposing it to ordinary seed saves'=>!in_array('winter_sowing_suitability',seed_columns(),true)&&str_contains(file_get_contents($root.'/app/seeds.php'),"'winter_sowing_suitability','winter_sowing_months','cold_stratification','winter_hardiness','winter_sowing_notes','winter_sowing_citation'"),
];
$failed=0;foreach($checks as $name=>$ok){echo($ok?'PASS':'FAIL').": $name\n";if(!$ok)$failed++;}exit($failed?1:0);
