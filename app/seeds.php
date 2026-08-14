<?php
declare(strict_types=1);

function seed_filter_validation_errors(array $filters): array
{
    $errors=[];
    foreach (['planting_start_from'=>'Start Planting Date “From”','planting_start_to'=>'Start Planting Date “To”','planting_end_from'=>'Last Planting Date “From”','planting_end_to'=>'Last Planting Date “To”'] as $key=>$label) {
        $value=trim((string)($filters[$key]??''));
        if ($value!=='' && !valid_mmdd($value)) $errors[]="$label must be a valid MM-DD date.";
    }
    return $errors;
}

function seed_filter_parts(array $filters): array
{
    $filterErrors=seed_filter_validation_errors($filters);
    if ($filterErrors) throw new InvalidArgumentException(implode(' ', $filterErrors));
    $where = [];
    $params = [];
    if (($filters['search'] ?? '') !== '') {
        $where[] = '(s.seed_number LIKE ? OR s.name LIKE ? OR s.variety LIKE ? OR c.name LIKE ? OR pf.name LIKE ? OR s.plant_type LIKE ? OR s.notes LIKE ? OR EXISTS (SELECT 1 FROM seed_uses su JOIN uses u ON u.id=su.use_id WHERE su.seed_id=s.id AND u.name LIKE ?) OR EXISTS (SELECT 1 FROM companion_relationships cr JOIN seeds cs ON cs.id=IF(cr.seed_id=s.id,cr.companion_seed_id,cr.seed_id) WHERE (cr.seed_id=s.id OR cr.companion_seed_id=s.id) AND (cs.name LIKE ? OR cs.variety LIKE ? OR cs.seed_number LIKE ?)))';
        $term = '%' . $filters['search'] . '%';
        array_push($params, ...array_fill(0, 11, $term));
    }
    foreach (['category_id' => 's.category_id', 'plant_family_id' => 's.plant_family_id', 'status_id' => 's.status_id', 'packet_year' => 's.packet_year'] as $key => $column) {
        if (($filters[$key] ?? '') !== '') { $where[] = "$column = ?"; $params[] = $filters[$key]; }
    }
    if (($filters['planting_method'] ?? '') !== '') {
        $where[] = 's.planting_method = ?';
        $params[] = $filters['planting_method'];
    }
    foreach (['plant_type' => 's.plant_type', 'seed_source' => 's.seed_source', 'companion' => null] as $key => $column) {
        if ($key === 'companion' && ($filters[$key] ?? '') !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM companion_relationships cr JOIN seeds cs ON cs.id=IF(cr.seed_id=s.id,cr.companion_seed_id,cr.seed_id) WHERE (cr.seed_id=s.id OR cr.companion_seed_id=s.id) AND (cs.name LIKE ? OR cs.variety LIKE ? OR cs.seed_number LIKE ?))';
            $term='%' . $filters[$key] . '%'; array_push($params,$term,$term,$term); continue;
        }
        if (($filters[$key] ?? '') !== '') { $where[] = "$column LIKE ?"; $params[] = '%' . $filters[$key] . '%'; }
    }
    if (($filters['storage_box'] ?? '') !== '') { $where[] = 'CONCAT_WS(\' \', l.storage_box, l.container, l.envelope, l.row_label, l.slot, l.notes) LIKE ?'; $params[] = '%' . $filters['storage_box'] . '%'; }
    foreach (seed_boolean_columns() as $flag) {
        if (($filters[$flag] ?? '') !== '') { $where[] = "s.$flag = ?"; $params[] = (int)$filters[$flag]; }
    }
    foreach (['indoor_start'=>['s.planting_method = \'Start Indoors\' OR s.indoor_start_month IS NOT NULL'], 'direct_sow'=>["s.planting_method IN ('Direct Sow','Direct Sow or Transplant') OR s.direct_sow_start_month IS NOT NULL"], 'transplant'=>["s.planting_method IN ('Transplant','Direct Sow or Transplant') OR s.transplant_start_month IS NOT NULL"]] as $key=>$conditions) {
        if (($filters[$key] ?? '') !== '') { $where[] = ((int)$filters[$key] === 1 ? '(' . $conditions[0] . ')' : 'NOT (COALESCE(' . $conditions[0] . ', 0))'); }
    }
    $useIds=array_values(array_filter((array)($filters['uses']??[]), fn($id)=>ctype_digit((string)$id)));
    if ($useIds) {
        $where[]='(SELECT COUNT(DISTINCT su.use_id) FROM seed_uses su WHERE su.seed_id=s.id AND su.use_id IN (' . implode(',',array_fill(0,count($useIds),'?')) . ')=?';
        array_push($params,...array_map('intval',$useIds)); $params[]=count($useIds);
    }
    if (($filters['plantable_month'] ?? '') !== '') {
        $where[] = plantable_in_month_sql('s');
        $m = (int)$filters['plantable_month'];
        array_push($params, $m, $m, $m, $m);
    }
    foreach (['planting_start'=>'planting_start','planting_end'=>'planting_end'] as $key=>$prefix) {
        $from=trim((string)($filters[$key.'_from']??'')); $to=trim((string)($filters[$key.'_to']??''));
        $monthColumn=$key==='planting_start'?'s.planting_start_month':'s.planting_end_month'; $dayColumn=$key==='planting_start'?'s.planting_start_day':'s.planting_end_day';
        $expression="($monthColumn * 100 + $dayColumn)";
        $fromValue=null; $toValue=null;
        if (valid_mmdd($from)) { [$m,$d]=array_map('intval',explode('-',$from)); $fromValue=$m*100+$d; }
        if (valid_mmdd($to)) { [$m,$d]=array_map('intval',explode('-',$to)); $toValue=$m*100+$d; }
        if ($fromValue!==null && $toValue!==null && $fromValue>$toValue) { $where[]="($expression >= ? OR $expression <= ?)"; array_push($params,$fromValue,$toValue); }
        else {
            if ($fromValue!==null) { $where[]="$expression >= ?"; $params[]=$fromValue; }
            if ($toValue!==null) { $where[]="$expression <= ?"; $params[]=$toValue; }
        }
    }
    return [$where,$params];
}

function seed_query(array $filters = [], bool $paginate = false): array
{
    $from = ' FROM seeds s LEFT JOIN categories c ON c.id=s.category_id LEFT JOIN plant_families pf ON pf.id=s.plant_family_id LEFT JOIN statuses st ON st.id=s.status_id LEFT JOIN seed_locations l ON l.seed_id=s.id';
    [$where,$params]=seed_filter_parts($filters); $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
    $sortMap=['seed_number'=>'s.seed_number','name'=>'s.name','variety'=>'s.variety','category_name'=>'c.name','family_name'=>'pf.name','plant_type'=>'s.plant_type','planting_method'=>'s.planting_method','germination'=>'COALESCE(s.days_to_germination_min, s.days_to_germination_max)','maturity'=>'COALESCE(s.days_to_maturity_min, s.days_to_maturity_max, s.days_to_maturity)','seed_source'=>'s.seed_source','packet_year'=>'s.packet_year','status_name'=>'st.name','planting_start'=>'s.planting_start_month * 100 + s.planting_start_day','planting_start_month'=>'s.planting_start_month * 100 + s.planting_start_day','planting_end'=>'s.planting_end_month * 100 + s.planting_end_day','planting_end_month'=>'s.planting_end_month * 100 + s.planting_end_day','storage_box'=>'l.storage_box'];
    $sort=$sortMap[$filters['sort']??'seed_number']??$sortMap['seed_number'];
    $direction = strtoupper($filters['direction'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
    $total=null; $overallTotal=null; $page=1; $perPage=null; $pages=null;
    if ($paginate) {
        $count=db()->prepare('SELECT COUNT(*)'.$from.$whereSql); $count->execute($params); $total=(int)$count->fetchColumn();
        $overallTotal=(int)db()->query('SELECT COUNT(*) FROM seeds')->fetchColumn();
        $allowed=[25,50,100,200]; $perPage=in_array((int)($filters['per_page']??25),$allowed,true)?(int)($filters['per_page']??25):25;
        $pages=(int)ceil($total/$perPage);
        $page=$pages===0?0:min(max(1,(int)($filters['page']??1)),$pages);
    }
    $sql='SELECT s.*, c.name AS category_name, pf.name AS family_name, st.name AS status_name, l.storage_box, l.container, l.envelope, l.row_label, l.slot, l.notes AS location_notes'.$from.$whereSql." ORDER BY $sort $direction, s.name ASC, s.id ASC";
    if ($paginate) $sql.=' LIMIT '.(int)$perPage.' OFFSET '.(max(0,$page-1)*(int)$perPage);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows=$stmt->fetchAll();
    return $paginate?['rows'=>$rows,'total'=>$total,'overall_total'=>$overallTotal,'page'=>$page,'per_page'=>$perPage,'pages'=>$pages]:$rows;
}

function seed_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT s.*, c.name AS category_name, pf.name AS family_name, st.name AS status_name, l.storage_box, l.container, l.envelope, l.row_label, l.slot, l.notes AS location_notes FROM seeds s LEFT JOIN categories c ON c.id=s.category_id LEFT JOIN plant_families pf ON pf.id=s.plant_family_id LEFT JOIN statuses st ON st.id=s.status_id LEFT JOIN seed_locations l ON l.seed_id=s.id WHERE s.id=?');
    $stmt->execute([$id]);
    $seed = $stmt->fetch();
    return $seed ?: null;
}

function seed_uses_for(int $id): array
{
    $stmt = db()->prepare('SELECT u.* FROM uses u JOIN seed_uses su ON su.use_id=u.id WHERE su.seed_id=? ORDER BY u.name');
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

function seed_companions_for(int $id): array
{
    $stmt = db()->prepare('SELECT cr.*, s.name, s.variety, s.seed_number FROM companion_relationships cr JOIN seeds s ON s.id=cr.companion_seed_id WHERE cr.seed_id=? ORDER BY cr.relationship_type, s.name');
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}


function seed_history_for(int $id): array
{
    $stmt = db()->prepare('SELECT h.*, u.name AS user_name FROM seed_history h LEFT JOIN users u ON u.id=h.user_id WHERE h.seed_id=? ORDER BY h.created_at DESC LIMIT 20');
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

function seed_nullable_int(mixed $value, string $label): ?int
{
    if ($value === null || $value === '') return null;
    if (is_int($value)) return $value;
    if (!is_string($value) || !preg_match('/^-?\d+$/D',$value)) throw new RuntimeException("$label must be a whole number.");
    return (int)$value;
}

function failed_seed_form_state(array $stored, array $submitted): array
{
    $state=array_merge($stored,$submitted);
    foreach (['container_friendly','trellis_needed','frost_tolerant','heat_tolerant','drought_tolerant','pollinator_friendly','medicinal'] as $flag) $state[$flag]=isset($submitted[$flag])?1:0;
    return $state;
}


function normalize_plantable_months(mixed $months): ?string
{
    if ($months===null || $months==='' || $months===[]) return null;
    $values=is_array($months)?$months:explode(',',(string)$months); $normalized=[];
    foreach ($values as $month) {
        if (!is_int($month) && (!is_string($month) || !preg_match('/^\d+$/D',$month))) throw new RuntimeException('Each Plantable Month must be a whole-number month from 1 through 12.');
        $number=(int)$month; if ($number<1 || $number>12) throw new RuntimeException('Each Plantable Month must be between 1 and 12.');
        if (isset($normalized[$number])) throw new RuntimeException('The same Plantable Month cannot be selected more than once.');
        $normalized[$number]=true;
    }
    return implode(',',array_keys($normalized));
}

function seed_payload(): array
{
    $data = [];
    foreach (seed_columns() as $column) {
        if ($column === 'plantable_months') { continue; }
        if (in_array($column, seed_boolean_columns(), true)) {
            $data[$column] = bool_value($column);
        } elseif (in_array($column, seed_integer_columns(), true)) {
            $data[$column] = seed_nullable_int($_POST[$column] ?? null, ucwords(str_replace('_', ' ', preg_replace('/_id$/', '', $column))));
        } else {
            $value = trim((string)($_POST[$column] ?? ''));
            $data[$column] = $value === '' ? null : $value;
        }
    }
    $data['plantable_months']=normalize_plantable_months($_POST['plantable_months']??[]);
    if ($data['perennial_status'] !== null) { $data['perennial'] = $data['perennial_status'] === 'Perennial' ? 1 : 0; }
    $data['seed_number'] = (string)($_POST['seed_number'] ?? '');
    $data['name'] = trim((string)($_POST['name'] ?? ''));
    return $data;
}

function has_duplicate_use_ids(array $uses): bool
{
    $keys=array_map(fn($id)=>ctype_digit((string)$id)?(string)(int)$id:'invalid:'.(string)$id,$uses); return count($keys)!==count(array_unique($keys));
}

function has_duplicate_companion_pairs(array $companions): bool
{
    $seen=[];
    foreach ($companions as $row) {
        $id=(string)($row['companion_seed_id']??''); $type=(string)($row['relationship_type']??'');
        if ($id==='' && $type==='') continue;
        $key=(ctype_digit($id)?(string)(int)$id:'invalid:'.$id).'|'.$type; if (isset($seen[$key])) return true; $seen[$key]=true;
    }
    return false;
}

function validate_seed(array $data, array $uses = [], array $companions = [], ?int $seedId = null): array
{
    $errors = [];
    if (trim((string)($data['seed_number'] ?? '')) === '') { $errors[] = 'Seed Number is required.'; }
    if (mb_strlen((string)($data['seed_number'] ?? '')) > 100) { $errors[] = 'Seed Number must be 100 characters or fewer.'; }
    if (trim((string)($data['name'] ?? '')) === '') { $errors[] = 'Seed Name is required.'; }
    if (mb_strlen((string)($data['name'] ?? '')) > 190) { $errors[] = 'Seed Name must be 190 characters or fewer.'; }
    $varcharLimits = [
        'variety'=>['Variety',190], 'plant_type'=>['Plant Type',80], 'sun_requirements'=>['Sun Requirements',120],
        'water_requirements'=>['Water Needs',120], 'soil_requirements'=>['Soil Preference',190], 'spacing'=>['Plant Spacing',120],
        'sowing_depth'=>['Planting Depth',80], 'ideal_soil_temperature'=>['Ideal Soil Temperature',80], 'row_spacing'=>['Row Spacing',120],
        'thin_to_spacing'=>['Thin-To Spacing',120], 'minimum_container_size'=>['Minimum Container Size',120], 'plant_height'=>['Plant Height',80],
        'seed_source'=>['Seed Source/Brand',190], 'quantity'=>['Quantity Notes',100],
    ];
    foreach ($varcharLimits as $key=>[$label,$limit]) if (mb_strlen((string)($data[$key]??''))>$limit) $errors[]="$label must be $limit characters or fewer.";
    foreach ([['category_id','categories','Category',true],['plant_family_id','plant_families','Plant Family',false],['status_id','statuses','Status',false]] as [$key,$table,$label,$required]) {
        if ($required && empty($data[$key])) { $errors[] = "$label is required."; }
        elseif (!empty($data[$key]) && !record_exists($table, (int)$data[$key])) { $errors[] = "$label selection is invalid."; }
    }
    $methods = ['Direct Sow','Start Indoors','Transplant','Direct Sow or Transplant'];
    if (empty($data['planting_method'])) { $errors[] = 'Planting Method is required.'; }
    elseif (!in_array($data['planting_method'], $methods, true)) { $errors[] = 'Planting Method is invalid.'; }
    $pairs = [
        ['planting_start_month','planting_start_day','Start Planting Date',true],['planting_end_month','planting_end_day','Last Recommended Planting Date',true],
        ['indoor_start_month','indoor_start_day','Indoor Start range start',false],['indoor_end_month','indoor_end_day','Indoor Start range end',false],
        ['direct_sow_start_month','direct_sow_start_day','Direct Sow range start',false],['direct_sow_end_month','direct_sow_end_day','Direct Sow range end',false],
        ['transplant_start_month','transplant_start_day','Transplant range start',false],['transplant_end_month','transplant_end_day','Transplant range end',false],
    ];
    foreach ($pairs as [$mk,$dk,$label,$required]) {
        $m=$data[$mk]??null; $d=$data[$dk]??null;
        if ($required && ($m===null || $d===null)) { $errors[]="$label is required."; continue; }
        if (($m===null) xor ($d===null)) { $errors[]="$label needs both month and day."; }
        elseif ($m!==null && !valid_month_day((int)$m,(int)$d)) { $errors[]="$label is not a real calendar date."; }
    }
    foreach (['indoor'=>['indoor_start_month','indoor_start_day','indoor_end_month','indoor_end_day','Indoor Start Range'], 'direct_sow'=>['direct_sow_start_month','direct_sow_start_day','direct_sow_end_month','direct_sow_end_day','Direct Sow Range'], 'transplant'=>['transplant_start_month','transplant_start_day','transplant_end_month','transplant_end_day','Transplant Range']] as [$sm,$sd,$em,$ed,$label]) {
        $startComplete=($data[$sm]??null)!==null && ($data[$sd]??null)!==null; $endComplete=($data[$em]??null)!==null && ($data[$ed]??null)!==null;
        $any=($data[$sm]??null)!==null || ($data[$sd]??null)!==null || ($data[$em]??null)!==null || ($data[$ed]??null)!==null;
        if ($any && (!$startComplete || !$endComplete)) $errors[]="$label requires both complete start and end dates.";
    }
    $months = $data['plantable_months'] === null || $data['plantable_months'] === '' ? [] : explode(',', (string)$data['plantable_months']);
    if (count($months) !== count(array_unique($months)) || array_filter($months, fn($m) => !ctype_digit($m) || (int)$m < 1 || (int)$m > 12)) { $errors[]='Plantable Months contains an invalid month.'; }
    foreach (seed_integer_columns() as $key) { if (!str_ends_with($key,'_id') && ($data[$key]??null)!==null && $data[$key] < 0) $errors[] = ucwords(str_replace('_',' ',$key)).' cannot be negative.'; }
    if (($data['days_to_maturity_min']??null)!==null && ($data['days_to_maturity_max']??null)!==null && $data['days_to_maturity_min']>$data['days_to_maturity_max']) $errors[]='Maturity minimum cannot exceed maturity maximum.';
    foreach (['indoor_start_status','direct_sow_status','transplant_status'] as $key) if (($data[$key]??null)!==null && !in_array($data[$key],['Not Recommended','Not Applicable'],true)) $errors[]=ucwords(str_replace('_',' ',$key)).' is invalid.';
    if (mb_strlen((string)($data['maturity_qualifier']??''))>120) $errors[]='Maturity Qualifier must be 120 characters or fewer.';
    if (($data['days_to_germination_min']??null)!==null && ($data['days_to_germination_max']??null)!==null && $data['days_to_germination_min']>$data['days_to_germination_max']) $errors[]='Germination minimum cannot exceed germination maximum.';
    $maxYear=(int)date('Y')+25; foreach(['packet_year','expiration_year'] as $key) if (($data[$key]??null)!==null && ($data[$key]<1900 || $data[$key]>$maxYear)) $errors[]=ucwords(str_replace('_',' ',$key))." must be between 1900 and $maxYear.";
    if (!valid_date_string($data['purchase_date']??null)) $errors[]='Purchase Date must be a real date.';
    if (($data['perennial_status']??null)!==null && !in_array($data['perennial_status'],['Annual','Biennial','Perennial'],true)) $errors[]='Perennial/Biennial Status is invalid.';
    if (has_duplicate_use_ids($uses)) $errors[]='The same Garden Use cannot be selected more than once.';
    foreach ($uses as $useId) if (!ctype_digit((string)$useId) || !record_exists('uses',(int)$useId)) $errors[]='A selected Garden Use is invalid.';
    $types=['Good Companion','Avoid','Neutral','Pest Deterrent','Trap Crop','Support Plant','Pollinator Support'];
    if (has_duplicate_companion_pairs($companions)) $errors[]='The same companion and relationship type cannot be entered more than once.';
    foreach ($companions as $i=>$row) {
        $cid=$row['companion_seed_id']??''; $type=$row['relationship_type']??'';
        if ($cid==='' && $type==='') continue;
        if (!ctype_digit((string)$cid) || !record_exists('seeds',(int)$cid)) $errors[]='Companion row '.($i+1).' references an invalid seed.';
        if ($seedId && (int)$cid===$seedId) $errors[]='A seed cannot be its own companion.';
        if (!in_array($type,$types,true)) $errors[]='Companion row '.($i+1).' has an invalid relationship type.';
    }
    foreach (['storage_box'=>['Storage Box',120],'container'=>['Container',120],'envelope'=>['Envelope',120],'row_label'=>['Row',80],'slot'=>['Slot',80]] as $field=>[$label,$limit])
        if (mb_strlen(trim((string)($_POST[$field]??'')))>$limit) $errors[]="$label must be $limit characters or fewer.";
    return array_values(array_unique($errors));
}

function history_value(string $key, mixed $value): mixed
{
    $tables=['category_id'=>'categories','plant_family_id'=>'plant_families','status_id'=>'statuses'];
    if ($value === null || !isset($tables[$key])) return $value;
    $stmt=db()->prepare('SELECT name FROM '.$tables[$key].' WHERE id=?'); $stmt->execute([(int)$value]);
    return $stmt->fetchColumn() ?: $value;
}

function companion_history_summary(array $rows): array
{
    $summary=[];
    foreach ($rows as $row) {
        $id=(int)($row['companion_seed_id']??0); $type=trim((string)($row['relationship_type']??''));
        if (!$id || $type==='') continue;
        $name=trim((string)($row['name']??'Companion')); $number=trim((string)($row['seed_number']??'')); $notes=trim((string)($row['notes']??''));
        $identity=$id.'|'.$type.'|'.$notes;
        $summary[$identity]=$name.($number!==''?' #'.$number:'').' — '.$type.($notes!==''?' ('.$notes.')':'');
    }
    ksort($summary,SORT_NATURAL); return $summary;
}

function history_friendly_value(string $key, mixed $value): mixed
{
    if ($value===null || $value==='') return $value;
    if (in_array($key,seed_boolean_columns(),true)) return (int)$value===1?'Yes':'No';
    if ($key==='plantable_months') return implode(', ',array_map(fn($m)=>month_name((int)$m),array_filter(explode(',',(string)$value))));
    return history_value($key,$value);
}

function seed_save(?int $id): int
{
    $data=seed_payload(); $uses=is_array($_POST['uses']??null)?$_POST['uses']:[]; $companions=is_array($_POST['companions']??null)?$_POST['companions']:[];
    $errors=validate_seed($data,$uses,$companions,$id); if ($errors) throw new RuntimeException(implode(' ', $errors));
    $before=$id ? seed_find($id) : null; $beforeUses=$id ? array_column(seed_uses_for($id),'name') : []; $beforeCompanions=$id ? seed_companions_for($id) : [];
    db()->beginTransaction();
    try {
        $columns=array_keys($data);
        if ($id) { $assign=implode(', ',array_map(fn($c)=>"$c = ?",$columns)); db()->prepare("UPDATE seeds SET $assign WHERE id = ?")->execute([...array_values($data),$id]); $seedId=$id; }
        else { $ph=implode(',',array_fill(0,count($columns),'?')); db()->prepare('INSERT INTO seeds ('.implode(',',$columns).") VALUES ($ph)")->execute(array_values($data)); $seedId=(int)db()->lastInsertId(); }
        save_location($seedId); save_seed_uses($seedId,$uses); save_companion_rows($seedId,$companions);
        $after=seed_find($seedId); $changes=[];
        if ($before) foreach (array_merge(array_values(array_diff(seed_columns(),['perennial'])),['storage_box','container','envelope','row_label','slot','location_notes']) as $key) if (($before[$key]??null)!==($after[$key]??null)) $changes[$key]=['before'=>history_friendly_value($key,$before[$key]??null),'after'=>history_friendly_value($key,$after[$key]??null)];
        $afterUses=array_column(seed_uses_for($seedId),'name'); sort($beforeUses); sort($afterUses); if ($before && $beforeUses!==$afterUses) $changes['uses']=['before'=>implode(', ',$beforeUses),'after'=>implode(', ',$afterUses)];
        $beforeCompanionSummary=companion_history_summary($beforeCompanions); $afterCompanionSummary=companion_history_summary(seed_companions_for($seedId));
        if ($before && $beforeCompanionSummary!==$afterCompanionSummary) $changes['companions']=['before'=>implode('; ',array_values($beforeCompanionSummary)),'after'=>implode('; ',array_values($afterCompanionSummary))];
        if (!$id || $changes) log_history($seedId,$id?'updated':'created',$changes); db()->commit(); return $seedId;
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
}

function save_location(int $seedId): void
{
    $fields = ['storage_box','container','envelope','row_label','slot','location_notes'];
    $values = [];
    foreach ($fields as $field) { $values[$field] = trim((string)($_POST[$field] ?? '')) ?: null; }
    $stmt = db()->prepare('INSERT INTO seed_locations (seed_id, storage_box, container, envelope, row_label, slot, notes) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE storage_box=VALUES(storage_box), container=VALUES(container), envelope=VALUES(envelope), row_label=VALUES(row_label), slot=VALUES(slot), notes=VALUES(notes)');
    $stmt->execute([$seedId, $values['storage_box'], $values['container'], $values['envelope'], $values['row_label'], $values['slot'], $values['location_notes']]);
}

function save_seed_uses(int $seedId, array $uses): void
{
    db()->prepare('DELETE FROM seed_uses WHERE seed_id=?')->execute([$seedId]);
    $stmt = db()->prepare('INSERT INTO seed_uses (seed_id, use_id) VALUES (?, ?)');
    foreach ($uses as $useId) { if ($useId !== '') { $stmt->execute([$seedId, (int)$useId]); } }
}

function save_companion_rows(int $seedId, array $rows): void
{
    db()->prepare('DELETE FROM companion_relationships WHERE seed_id=?')->execute([$seedId]);
    $stmt = db()->prepare('INSERT INTO companion_relationships (seed_id, companion_seed_id, relationship_type, notes) VALUES (?, ?, ?, ?)');
    foreach ($rows as $row) {
        $companionId = (int)($row['companion_seed_id'] ?? 0);
        $type = $row['relationship_type'] ?? '';
        if ($companionId > 0 && $companionId !== $seedId && $type !== '') {
            $stmt->execute([$seedId, $companionId, $type, trim((string)($row['notes'] ?? '')) ?: null]);
        }
    }
}

function duplicate_seed(int $id): int
{
    $pdo=db(); $ownsTransaction=!$pdo->inTransaction(); $savepoint='duplicate_seed';
    if ($ownsTransaction) $pdo->beginTransaction(); else $pdo->exec("SAVEPOINT $savepoint");
    try {
        $seed=seed_find($id); if (!$seed) throw new RuntimeException('Seed not found.');
        $data=array_intersect_key($seed,array_flip(seed_columns())); $data['name']=$data['name'].' (Copy)'; $columns=array_keys($data);
        $stmt=$pdo->prepare('INSERT INTO seeds ('.implode(', ',$columns).') VALUES ('.implode(', ',array_fill(0,count($columns),'?')).')'); $stmt->execute(array_values($data));
        $newId=(int)$pdo->lastInsertId();
        $oldPost=$_POST; $_POST=array_merge($_POST,array_intersect_key($seed,array_flip(['storage_box','container','envelope','row_label','slot','location_notes'])));
        try { save_location($newId); } finally { $_POST=$oldPost; }
        $pdo->prepare('INSERT INTO seed_uses (seed_id, use_id) SELECT ?, use_id FROM seed_uses WHERE seed_id=?')->execute([$newId,$id]);
        $pdo->prepare('INSERT INTO companion_relationships (seed_id, companion_seed_id, relationship_type, notes) SELECT ?, companion_seed_id, relationship_type, notes FROM companion_relationships WHERE seed_id=?')->execute([$newId,$id]);
        log_history($newId,'duplicated',['source_seed_id'=>$id]);
        if ($ownsTransaction) $pdo->commit(); else $pdo->exec("RELEASE SAVEPOINT $savepoint");
        return $newId;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack(); elseif (!$ownsTransaction) $pdo->exec("ROLLBACK TO SAVEPOINT $savepoint");
        throw $e;
    }
}

function delete_seed(int $id): void
{
    $stmt = db()->prepare('DELETE FROM seeds WHERE id=?');
    $stmt->execute([$id]);
    log_history(null, 'deleted', ['seed_id' => $id]);
}
