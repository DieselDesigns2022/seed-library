<?php
declare(strict_types=1);
$root=dirname(__DIR__); require_once $root.'/app/bootstrap.php'; require_once $root.'/app/seeds.php';
$index=file_get_contents($root.'/public/index.php'); $seeds=file_get_contents($root.'/app/seeds.php'); $inventory=file_get_contents($root.'/app/templates/seeds_index.php'); $detail=file_get_contents($root.'/app/templates/seed_detail.php'); $view=file_get_contents($root.'/app/view.php'); $schema=file_get_contents($root.'/database/schema.sql'); $migration=file_get_contents($root.'/database/2026-08-14-phase-5-settings-defaults.sql');
$GLOBALS['display_exact_dates_override']=true; $exact=date_label(5,5);
$GLOBALS['display_exact_dates_override']=false; $monthOnly=date_label(5,5);
$GLOBALS['display_plantable_months_override']=false; $plantableHidden=!display_plantable_months();
unset($GLOBALS['display_exact_dates_override'],$GLOBALS['display_plantable_months_override']);
$required=['Vegetable','Fruit','Medicinal Herb','Native Plant','Solanaceae','Plantaginaceae','Fresh Eating','Ornamental','In Seed Bank','Save for Next Year'];
$tests=[
 'idempotent starter migration preserves existing values'=>str_contains($migration,'INSERT IGNORE')&&!str_contains($migration,'DELETE')&&!array_filter($required,fn($v)=>!str_contains($migration,"('$v'")),
 'lookup writes are owner/CSRF protected and deletion checks references'=>str_contains($index,'function manage_page')&&substr_count($index,'require_owner();')>=3&&str_contains($index,'Reassign those seeds before deleting it')&&str_contains($index,'verify_csrf();'),
 'storage management keeps number read-only'=>str_contains($index,'function storage_page')&&str_contains($index,'Seed Number is shown only')&&!str_contains($index,'name="seed_number"')&&str_contains($view,'manage/storage'),
 'garden and display settings are validated and backup is linked'=>str_contains($index,"'garden_notes'")&&str_contains($index,'valid reusable MM-DD')&&str_contains($index,'Database Backup &amp; Restore'),
 'date and plantable display behavior changes'=>$exact==='May 5'&&$monthOnly==='May'&&$plantableHidden&&substr_count($inventory,'if(display_plantable_months())')>=2&&str_contains($detail,"if(display_plantable_months())")&&!str_contains($inventory.$detail.$seeds,'Hidden by display setting'),
 'managed storage history records changes but skips no-op saves'=>storage_history_changes(['storage_box'=>'Box 1','slot'=>'2'],['storage_box'=>'Box 2','slot'=>'2'])===['storage_box'=>['before'=>'Box 1','after'=>'Box 2']]&&storage_history_changes(['storage_box'=>'Box 1'],['storage_box'=>'Box 1'])===[]&&str_contains($index,'log_history($seedId,\'updated\',$changes)'),
 'lookup limits match schema'=>str_contains($index,"'categories'=>100")&&str_contains($index,"'families','uses'=>120")&&str_contains($index,"'statuses'=>80"),
 'fresh category descriptions are preserved'=>str_contains($schema,"('Vegetable','Edible vegetable crops')")&&str_contains($schema,"('Herb','Culinary herbs')")&&str_contains($schema,"('Flower','Flowering plants')")&&str_contains($schema,"('Medicinal','Medicinal plants')"),
 'inventory defaults and explicit overrides are wired'=>str_contains($seeds,"setting_choice('default_inventory_sort'")&&str_contains($seeds,"filters['sort']")&&str_contains($seeds,"filters['per_page']")&&str_contains($seeds,"setting_choice('rows_per_page'"),
 'seed number has natural and exact-text modes'=>str_contains($seeds,"setting_choice('seed_number_order'")&&str_contains($seeds,'CAST(s.seed_number AS UNSIGNED)')
];
$failed=0; foreach($tests as $name=>$ok){echo($ok?'PASS':'FAIL').": $name\n"; if(!$ok)$failed++;} exit($failed?1:0);
