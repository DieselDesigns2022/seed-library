<?php
declare(strict_types=1);
$root=dirname(__DIR__);$form=file_get_contents($root.'/app/templates/seed_form.php');$detail=file_get_contents($root.'/app/templates/seed_detail.php');$migration=file_get_contents($root.'/database/2026-08-14-phase-4-growing-data.sql');require_once $root.'/app/bootstrap.php';require_once $root.'/app/seeds.php';require_once $root.'/app/import_export.php';
$checks=[
 'maturity range display'=>maturity_display(['days_to_maturity_min'=>70,'days_to_maturity_max'=>110,'maturity_qualifier'=>'from transplant'])==='70–110 days from transplant',
 'single maturity display'=>maturity_display(['days_to_maturity_min'=>80,'days_to_maturity_max'=>80,'maturity_qualifier'=>null])==='80 days',
 'legacy maturity compatibility'=>maturity_display(['days_to_maturity'=>80])==='80 days',
 'availability range wins over status'=>planting_availability_display(['indoor_start_month'=>3,'indoor_start_day'=>1,'indoor_end_month'=>3,'indoor_end_day'=>20,'indoor_start_status'=>'Not Recommended'],'indoor')==='Mar 1 – Mar 20',
 'explicit planting statuses display'=>planting_availability_display(['direct_sow_status'=>'Not Recommended'],'direct_sow')==='Not Recommended'&&planting_availability_display(['transplant_status'=>'Not Applicable'],'transplant')==='Not Applicable',
 'empty planting availability is not inferred'=>planting_availability_display([],'indoor')==='Not recorded',
 'generic growing fields normalize'=>normalize_import_payload(['ideal_soil_temperature'=>'65–75 F','sowing_depth'=>'1/4 in','row_spacing'=>'18 in','thin_to_spacing'=>'6 in','water_requirements'=>'Moderate','soil_requirements'=>'Loam','plant_height'=>'24 in','minimum_container_size'=>'3 gal','indoor_start_weeks_before_frost'=>'6','transplant_weeks_after_frost'=>'2','succession_days'=>'14','days_to_maturity_min'=>'70','days_to_maturity_max'=>'110','maturity_qualifier'=>'from transplant','indoor_start_status'=>'Not Recommended','direct_sow_status'=>'Not Applicable'])['days_to_maturity_max']===110,
 'legacy maturity is not editable'=>!str_contains($form,'Legacy Maturity Days')&&!preg_match('/name=\"<\?=e\(\$key\)\?>\"[^>]*days_to_maturity/', $form),
 'planting fields stay out of Storage'=>!preg_match('/<h2 class=\"h5\">Storage<\/h2>.*?(days_to_maturity_min|maturity_qualifier|indoor_start_status|direct_sow_status|transplant_status)/s',$detail),
 'migration is rerunnable and non-destructive'=>str_contains($migration,'information_schema.COLUMNS')&&str_contains($migration,'IF NOT EXISTS')&&str_contains($migration,'DROP PROCEDURE')&&!preg_match('/\b(DELETE|DROP TABLE|TRUNCATE|REPLACE INTO)\b/i',$migration),
 'enrichment and exact identifiers remain importable'=>!array_diff(['garden_uses','good_companion_seed_numbers','avoid_companion_seed_numbers','seed_number'],import_destination_columns()),
];
$failed=[];foreach($checks as$name=>$ok){echo($ok?'PASS':'FAIL').": $name\n";if(!$ok)$failed[]=$name;}exit($failed?1:0);
