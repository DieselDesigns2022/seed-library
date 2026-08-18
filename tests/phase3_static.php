<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$index=file_get_contents($root.'/public/index.php');
$calendar=file_get_contents($root.'/app/calendar.php');
$form=file_get_contents($root.'/app/templates/seed_form.php');
$js=file_get_contents($root.'/public/assets/app.js');
$seeds=file_get_contents($root.'/app/seeds.php');
$schema=file_get_contents($root.'/database/schema.sql');
$migration=file_get_contents($root.'/database/2026-08-13-phase-3-statuses.sql');
$readme=file_get_contents($root.'/README.md');
$testing=file_get_contents($root.'/TESTING.md');
$databaseReadme=file_get_contents($root.'/database/README.md');
$checks=[
 'calendar has all twelve months and complete columns'=>str_contains($index,'for($m=1;$m<=12;$m++)')&&str_contains($index,'Days to Harvest/Maturity')&&str_contains($index,'View Seed'),
 'calendar uses cross-year/explicit-month query'=>str_contains($index,"seed_query(['plantable_month'=>\$month")&&str_contains(file_get_contents($root.'/app/bootstrap.php'),'FIND_IN_SET'),
 'method calendar groups use dedicated ranges with cross-year helper and general fallback'=>str_contains($calendar,"'direct_sow'=>['direct_sow'")&&str_contains($calendar,"'start_indoors'=>['indoor'")&&str_contains($calendar,"'transplant'=>['transplant'")&&str_contains($calendar,'calendar_month_in_range')&&str_contains($calendar,'calendar_general_month_matches')&&str_contains($index,"seed_query(['sort'=>'planting_start_month'])"),
 'calendar grouping is centralized and documented'=>str_contains($calendar,'function calendar_group_rules')&&str_contains($calendar,'Phase 3 inferred groups are centralized here'),
 'sparse companion indexes advance beyond the greatest used key'=>str_contains($form,'max(array_map(\'intval\',$companionIndexes))+1')&&!str_contains($form,'data-next-index="<?=count($companions)?>"'),
 'unlimited dynamic companions'=>!str_contains($form,'for($i=0;$i<6')&&str_contains($form,'companion-template')&&str_contains($js,'rows.dataset.nextIndex'),
 'companion validation remains structured'=>str_contains($seeds,'has_duplicate_companion_pairs')&&str_contains($seeds,"'Pollinator Support'")&&str_contains($seeds,'own companion'),
 'finder searches either endpoint without reversing meaning'=>str_contains($index,'source.name LIKE ?')&&str_contains($index,'target.name LIKE ?')&&str_contains($index,'source.id source_id')&&str_contains($index,'target.id target_id')&&str_contains($index,'source_name')&&str_contains($index,'target_name'),
 'finder suppresses duplicate seed/type results and merges details'=>str_contains($index,'function companion_finder_deduplicate')&&str_contains($index,"\$key=(int)\$row['seed_id'].'|'.\$row['relationship_type']")&&str_contains($index,"\$deduplicated[\$key]['_notes'][\$notes]=true")&&str_contains($index,"\$deduplicated[\$key]['_directions'][\$direction]=true")&&str_contains($index,"implode('; ',\$notes)")&&str_contains($index,"implode('; ',\$directions)"),
 'relationship direction rules are centralized and displayed'=>str_contains($index,'function companion_relationship_direction_rules')&&str_contains($index,"'Good Companion'=>'symmetric'")&&str_contains($index,"'Neutral'=>'symmetric'")&&str_contains($index,"'Pest Deterrent'=>'directional'")&&str_contains($index,"'Pollinator Support'=>'directional'")&&str_contains($index,'stored source → target direction')&&str_contains($index,'<th>Direction</th>'),
 'inventory companion search checks either side'=>str_contains($seeds,'cr.companion_seed_id=s.id'),
 'documentation reflects unlimited companions and passed Phase 2 smoke test'=>!str_contains($testing,'Uses, six companions, Plantable Months')&&str_contains($testing,'Phase 2 live smoke test passed')&&str_contains($readme,'Phase 2 live smoke test passed')&&str_contains($readme,'Good Companion, Avoid, and Neutral are symmetric')&&str_contains($testing,'Pollinator Support are directional'),
 'database README disambiguates migration idempotency'=>str_contains($databaseReadme,'The Phase 1 field migration')&&str_contains($databaseReadme,'Phase 3 status migration remains idempotent'),
 'database README documents idempotent Phase 3 migration'=>str_contains($databaseReadme,'2026-08-13-phase-3-statuses.sql')&&str_contains($databaseReadme,'safe to run more than once')&&str_contains($databaseReadme,'custom statuses'),
 'fresh and migrated status parity'=>substr_count($schema,"('In Seed Bank',1)")===1&&str_contains($migration,'ON DUPLICATE KEY UPDATE')&&str_contains($migration,"('Save for Next Year',1)"),
];
$failed=0;foreach($checks as $name=>$ok){echo($ok?'PASS':'FAIL').": $name\n";if(!$ok)$failed++;}exit($failed?1:0);
