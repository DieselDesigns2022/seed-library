<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$seeds=file_get_contents($root.'/app/seeds.php');
$inventory=file_get_contents($root.'/app/templates/seeds_index.php');
$dashboard=file_get_contents($root.'/public/index.php');
$imports=file_get_contents($root.'/app/import_export.php');
$readme=file_get_contents($root.'/README.md');
$testing=file_get_contents($root.'/TESTING.md');
$checks=[
 'global search covers lookup, use, and companion data'=>str_contains($seeds,'c.name LIKE ?')&&str_contains($seeds,'pf.name LIKE ?')&&str_contains($seeds,'seed_uses su')&&str_contains($seeds,'companion_relationships cr'),
 'relationship matching uses EXISTS to avoid duplicates'=>substr_count($seeds,'EXISTS (SELECT 1 FROM companion_relationships')>=2&&str_contains($seeds,'EXISTS (SELECT 1 FROM seed_uses'),
 'Planting Method filter uses exact matching'=>str_contains($seeds,"\$where[] = 's.planting_method = ?'")&&!preg_match("/planting_method[^\n]+LIKE/",$seeds),
 'invalid MM-DD filters are rejected without querying'=>str_contains($seeds,'function seed_filter_validation_errors')&&str_contains($seeds,'!valid_mmdd($value)')&&str_contains($seeds,'throw new InvalidArgumentException')&&str_contains($dashboard,'$filterErrors=seed_filter_validation_errors($filters)')&&str_contains($dashboard,'$filterErrors')&&str_contains($inventory,'Correct the planting date filters')&&str_contains($inventory,'if(!$filterErrors)')&&str_contains($testing,'no seed query/results are shown'),
 'all requested boolean and method filters include drought'=>!array_filter(['container_friendly','pollinator_friendly','medicinal','perennial','frost_tolerant','heat_tolerant','drought_tolerant','trellis_needed','indoor_start','direct_sow','transplant'],fn($v)=>!str_contains($inventory,$v)),
 'desktop and mobile show required growing data and flags'=>substr_count($inventory,'sun_requirements')>=2&&substr_count($inventory,"['spacing']")>=2&&substr_count($inventory,'perennial_status')>=2&&str_contains($inventory,'Important Flags')&&str_contains($inventory,"'drought_tolerant'=>'Drought'"),
 'optional growing values have clean formatters'=>str_contains($inventory,"return 'Not recorded'")&&str_contains($inventory,"\$germination(\$s)")&&str_contains($inventory,"\$maturity(\$s)")&&!str_contains($inventory,"??'—'?>–"),
 'location filter covers every Phase 1 location field'=>str_contains($seeds,"l.storage_box, l.container, l.envelope, l.row_label, l.slot, l.notes"),
 'server-side count, limit, and offset pagination are present'=>str_contains($seeds,"SELECT COUNT(*)")&&str_contains($seeds,"' LIMIT '")&&str_contains($seeds,"' OFFSET '"),
 'pagination metadata handles zero results'=>str_contains($seeds,'$pages===0?0')&&str_contains($seeds,"'overall_total'=>")&&str_contains($inventory,'Showing <?=e($showingStart)?>–<?=e($showingEnd)?>')&&str_contains($inventory,"Page <?=e(\$result['page'])?> of <?=e(\$result['pages'])?>"),
 'inventory distinguishes empty library and empty matches'=>str_contains($inventory,'Your seed library has no records yet')&&str_contains($inventory,'Seeds are saved in your library, but none match')&&str_contains($inventory,"\$result['overall_total']===0"),
 'rows-per-page allowlist is enforced'=>str_contains($seeds,'$allowed=[25,50,100,200]'),
 'safe sort map supports new fields and legacy calendar key'=>!array_filter(['germination','maturity','seed_source','planting_start_month'],fn($v)=>!str_contains($seeds,"'$v'=>"))&&!str_contains($seeds,'ORDER BY {$filters')&&str_contains($imports,"'sort'=>'planting_start_month'"),
 'filter/sort/page links preserve the active query'=>str_contains($inventory,'array_merge($active,$changes)')&&str_contains($inventory,"['page'=>\$p]"),
 'empty result, clear all, and responsive layouts exist'=>str_contains($inventory,'none match the current search and filters')&&str_contains($inventory,'Clear All / Reset')&&str_contains($inventory,'mobile-card'),
 'row actions include POST CSRF duplicate and delete'=>substr_count($inventory,'csrf_field()')>=4&&str_contains($inventory,"/duplicate'))")&&str_contains($inventory,"/delete'))"),
 'dashboard exposes every requested metric and quick action'=>!array_filter(['Total Seeds','Food Crops','Herbs','Medicinal Plants','Flowers','Pollinator-Friendly Plants','Container-Friendly Plants','Perennials','Direct-Sow Seeds','Start-Indoors Seeds','Seeds Plantable This Month','Seeds Past Their Recommended Planting Window','Dashboard Quick Search','View All Seeds','Planting Calendar','Companion Finder','Import Seeds'],fn($v)=>!str_contains($dashboard,$v)),
 'dashboard category rules are centralized and documented'=>str_contains($dashboard,'function dashboard_category_count_rules()')&&str_contains($dashboard,"'category_names'=>")&&str_contains($dashboard,"'include_medicinal_flag'=>")&&substr_count($dashboard,'LOWER(c.name)')===1,
 'dashboard provides separate Export and Print destinations'=>str_contains($dashboard,"url('export')")&&str_contains($dashboard,"url('print')")&&preg_match('/>Export<\/a>/', $dashboard)&&preg_match('/>Print<\/a>/', $dashboard),
 'verification docs separate static and live scope'=>str_contains($readme,'code-level verification')&&str_contains($readme,'do not imply that every item')&&str_contains($testing,'does not establish that every item'),
];
$failed=[]; foreach($checks as $label=>$ok){echo ($ok?'PASS':'FAIL').": $label\n"; if(!$ok)$failed[]=$label;} exit($failed?1:0);
