<?php
declare(strict_types=1);
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';require_once $root.'/app/garden.php';require_once $root.'/app/backup.php';
$schema=file_get_contents($root.'/database/schema.sql');$migration=file_get_contents($root.'/database/2026-08-19-phase-7-garden-winter-sowing.sql');$index=file_get_contents($root.'/public/index.php');$planner=file_get_contents($root.'/app/templates/winter_sowing.php');
$explicit=['winter_sowing_suitability'=>'Suitable','winter_sowing_months'=>'12,1','planting_method'=>'Direct Sow','frost_tolerant'=>0,'perennial'=>0];
$inferredOnly=['winter_sowing_suitability'=>'Unknown','winter_sowing_months'=>null,'planting_method'=>'Direct Sow','frost_tolerant'=>1,'perennial'=>1,'plantable_months'=>'12,1,2,3'];
$owner=['id'=>'1','name'=>'Owner','email'=>'owner@example.com','password_hash'=>password_hash('test-password',PASSWORD_DEFAULT),'is_owner'=>'1'];
$legacyTables=array_fill_keys(legacy_backup_tables(),[]);$legacyTables['users']=[$owner];$legacyTables['seeds']=[['id'=>'1','seed_number'=>'LEGACY-1','name'=>'Legacy Seed']];$currentTables=$legacyTables;$currentTables['garden_plantings']=[];
$legacy=['format'=>'seed-library-backup','version'=>1,'tables'=>$legacyTables];$current=['format'=>'seed-library-backup','version'=>2,'tables'=>$currentTables];
$checks=[
 'winter eligibility is explicit only'=>winter_seed_is_eligible($explicit,12)&&winter_seed_is_eligible($explicit,1)&&!winter_seed_is_eligible($explicit,2),
 'unresearched seed is never eligible'=>!winter_seed_is_eligible($inferredOnly,12),
 'December and January remain distinct explicit cross-year choices'=>array_keys(winter_sowing_month_choices())===[12,1,2,3],
 'arbitrary research choices and months are rejected'=>count(winter_validate(['winter_sowing_suitability'=>'Maybe','winter_sowing_months'=>[11],'cold_stratification'=>'Sometimes','winter_hardiness'=>'Medium']))>=4,
 'malformed winter months cannot normalize into eligibility'=>winter_submitted_months(['1abc'],false)===null&&winter_submitted_months(['2.5'],false)===null&&winter_submitted_months(['January'],false)===null,
 'winter months normalize in winter order'=>winter_submitted_months(['3','1','12','2'])===[12,1,2,3],
 'suitable seed can feed a Winter Sown garden action'=>str_contains($planner,"'method'=>'Winter Sown'")&&str_contains($index,'winter_seed_is_eligible'),
 'first frost before occurrence uses this year'=>recurring_date_countdown('10-15',new DateTimeImmutable('2026-10-14 18:00:00'))===1,
 'first frost on occurrence is zero'=>recurring_date_countdown('10-15',new DateTimeImmutable('2026-10-15 18:00:00'))===0,
 'first frost after occurrence rolls to next year'=>recurring_date_countdown('10-15',new DateTimeImmutable('2026-10-16'))===364,
 'last frost before occurrence uses this year'=>recurring_date_countdown('05-05',new DateTimeImmutable('2026-05-04'))===1,
 'last frost on occurrence is zero'=>recurring_date_countdown('05-05',new DateTimeImmutable('2026-05-05'))===0,
 'last frost after occurrence rolls to next year'=>recurring_date_countdown('05-05',new DateTimeImmutable('2026-05-06'))===364,
 'existing frost settings are not duplicated by migration'=>!str_contains($migration,'INSERT INTO settings')&&substr_count($schema,"'average_last_frost'")===1&&substr_count($schema,"'average_first_frost'")===1,
 'dashboard renders both countdown labels'=>str_contains($index,"'First Frost'=>recurring_date_countdown")&&str_contains($index,"'Last Frost'=>recurring_date_countdown"),
 'unavailable frost cards retain their identities and singular grammar exists'=>str_contains($index,"e(\$label).' Unavailable'")&&str_contains($index,"\$days===1?'Day':'Days'"),
 'current version validates with every current table'=>validate_backup_payload($current)['version']===2,
 'legacy version normalizes garden history and leaves missing research to schema defaults'=>(function()use($legacy){$validated=validate_backup_payload($legacy);return $validated['tables']['garden_plantings']===[]&&!array_key_exists('winter_sowing_suitability',$validated['tables']['seeds'][0]);})(),
 'incomplete current version is rejected'=>(function()use($current){unset($current['tables']['garden_plantings']);try{validate_backup_payload($current);return false;}catch(RuntimeException){return true;}})(),
 'unsupported backup version is rejected'=>(function()use($current){$current['version']=99;try{validate_backup_payload($current);return false;}catch(RuntimeException){return true;}})(),
];
$failed=0;foreach($checks as $name=>$ok){echo($ok?'PASS':'FAIL').": $name\n";if(!$ok)$failed++;}exit($failed?1:0);
