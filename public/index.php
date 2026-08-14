<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/view.php';
require __DIR__ . '/../app/seeds.php';
require __DIR__ . '/../app/import_export.php';
require __DIR__ . '/../app/backup.php';

$path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$base = trim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
if ($base && str_starts_with($path, $base)) { $path = trim(substr($path, strlen($base)), '/'); }
$path = $path ?: 'dashboard';

try {
    if ($path === 'login') { login_page(); return; }
    if ($path === 'logout') { logout_action(); return; }
    require_auth();
    match_route($path);
} catch (Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    render('Error', function () use ($e) {
        $message = app_debug() ? $e->getMessage() : 'Something went wrong. Please contact the administrator or check the server logs.';
        echo '<div class="alert alert-danger"><h1>Application Error</h1><p>' . e($message) . '</p></div>';
    });
}

function match_route(string $path): void
{
    if ($path === 'dashboard') { dashboard_page(); return; }
    if ($path === 'seeds') { seeds_page(); return; }
    if ($path === 'seeds/create') { seed_form_page(null); return; }
    if (preg_match('#^seeds/(\d+)$#', $path, $m)) { seed_detail_page((int)$m[1]); return; }
    if (preg_match('#^seeds/(\d+)/edit$#', $path, $m)) { seed_form_page((int)$m[1]); return; }
    if (preg_match('#^seeds/(\d+)/duplicate$#', $path, $m)) {
        require_post(); verify_csrf(); $sourceId=(int)$m[1];
        try { $id=duplicate_seed($sourceId); flash('success','Seed duplicated with the same physical seed number. Edit it only if you need a different label.'); redirect('seeds/'.$id.'/edit'); }
        catch (PDOException $e) { error_log((string)$e); flash('danger','The duplicate could not be created. The original seed was not changed.'); redirect('seeds/'.$sourceId); }
    }
    if (preg_match('#^seeds/(\d+)/delete$#', $path, $m)) { require_post(); verify_csrf(); delete_seed((int)$m[1]); flash('success','Seed deleted.'); redirect('seeds'); }
    if ($path === 'calendar') { calendar_page(); return; }
    if ($path === 'companions') { companions_page(); return; }
    if ($path === 'import') { import_page(); return; }
    if ($path === 'export') { export_page(); return; }
    if ($path === 'print') { print_page(); return; }
    if ($path === 'backup') { backup_page(); return; }
    if ($path === 'settings') { settings_page(); return; }
    if ($path === 'manage/storage') { storage_page(); return; }
    if (preg_match('#^manage/(categories|families|uses|statuses)$#', $path, $m)) { manage_page($m[1]); return; }
    http_response_code(404); render('Not Found', fn() => print '<h1>Not Found</h1>');
}

function login_page(): void
{
    if (current_user()) { redirect('dashboard'); }
    $_SESSION['login_attempts'] ??= [];
    $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], fn($time) => $time > time() - 900);
    $locked = count($_SESSION['login_attempts']) >= 5;
    if (is_post()) {
        verify_csrf();
        if ($locked) {
            flash('danger', 'Too many failed login attempts. Please wait 15 minutes and try again.');
            redirect('login');
        }
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([trim((string)($_POST['email'] ?? ''))]);
        $user = $stmt->fetch();
        if ($user && password_verify((string)($_POST['password'] ?? ''), $user['password_hash'])) {
            session_regenerate_id(true);
            unset($_SESSION['login_attempts']);
            $_SESSION['user_id'] = (int)$user['id'];
            flash('success', 'Welcome back, ' . $user['name'] . '.');
            redirect('dashboard');
        }
        $_SESSION['login_attempts'][] = time();
        flash('danger', 'Invalid email or password.');
    }
    render('Login', function () use ($locked) { ?>
    <div class="row justify-content-center"><div class="col-md-5 col-lg-4"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h3 mb-3">Seed Library Login</h1><?php if ($locked): ?><div class="alert alert-warning">Too many failed attempts. Please wait before trying again.</div><?php endif; ?><form method="post"><?= csrf_field() ?><div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required autofocus></div><div class="mb-3"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required></div><button class="btn btn-success w-100" <?= $locked ? 'disabled' : '' ?>>Login</button></form></div></div></div></div>
    <?php });
}

function logout_action(): void
{
    require_post();
    verify_csrf();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    redirect('login');
}

/**
 * Dashboard category rules are centralized here because categories remain
 * owner-managed names rather than typed records. Add normalized category names
 * to these lists as category management expands. Medicinal also honors its
 * dedicated seed flag; the other three metrics are category-only.
 */
function dashboard_category_count_rules(): array
{
    return [
        'Food Crops' => ['category_names'=>['vegetable','food crop','food crops'], 'include_medicinal_flag'=>false],
        'Herbs' => ['category_names'=>['herb'], 'include_medicinal_flag'=>false],
        'Medicinal Plants' => ['category_names'=>['medicinal'], 'include_medicinal_flag'=>true],
        'Flowers' => ['category_names'=>['flower'], 'include_medicinal_flag'=>false],
    ];
}

function dashboard_page(): void
{
    $month = (int)date('n');
    $today=(int)date('md');
    $counts = ['Total Seeds'=>(int)db()->query('SELECT COUNT(*) FROM seeds')->fetchColumn()];
    foreach (dashboard_category_count_rules() as $label=>$rule) {
        $placeholders=implode(',',array_fill(0,count($rule['category_names']),'?'));
        $condition='LOWER(c.name) IN ('.$placeholders.')';
        if ($rule['include_medicinal_flag']) $condition='(s.medicinal=1 OR '.$condition.')';
        $stmt=db()->prepare('SELECT COUNT(*) FROM seeds s LEFT JOIN categories c ON c.id=s.category_id WHERE '.$condition);
        $stmt->execute($rule['category_names']); $counts[$label]=(int)$stmt->fetchColumn();
    }
    $stats = [
        'Pollinator-Friendly Plants' => 'SELECT COUNT(*) FROM seeds WHERE pollinator_friendly=1',
        'Container-Friendly Plants' => 'SELECT COUNT(*) FROM seeds WHERE container_friendly=1',
        'Perennials' => 'SELECT COUNT(*) FROM seeds WHERE perennial=1',
        'Direct-Sow Seeds' => "SELECT COUNT(*) FROM seeds WHERE planting_method IN ('Direct Sow','Direct Sow or Transplant') OR direct_sow_start_month IS NOT NULL",
        'Start-Indoors Seeds' => "SELECT COUNT(*) FROM seeds WHERE planting_method='Start Indoors' OR indoor_start_month IS NOT NULL",
    ];
    foreach ($stats as $label => $sql) { $counts[$label] = (int)db()->query($sql)->fetchColumn(); }
    $stmt = db()->prepare('SELECT COUNT(*) FROM seeds s WHERE ' . plantable_in_month_sql('s'));
    $stmt->execute([$month,$month,$month,$month]);
    $counts['Seeds Plantable This Month'] = (int)$stmt->fetchColumn();
    $stmt = db()->prepare('SELECT COUNT(*) FROM seeds WHERE planting_start_month IS NOT NULL AND planting_start_day IS NOT NULL AND planting_end_month IS NOT NULL AND planting_end_day IS NOT NULL AND (((planting_start_month*100+planting_start_day) <= (planting_end_month*100+planting_end_day) AND ? > (planting_end_month*100+planting_end_day)) OR ((planting_start_month*100+planting_start_day) > (planting_end_month*100+planting_end_day) AND ? > (planting_end_month*100+planting_end_day) AND ? < (planting_start_month*100+planting_start_day)))');
    $stmt->execute([$today,$today,$today]);
    $counts['Seeds Past Their Recommended Planting Window'] = (int)$stmt->fetchColumn();
    render('Dashboard', function () use ($counts) { ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h1>Dashboard</h1><p class="text-muted mb-0">Zone <?= e(setting('zone','6B')) ?> · <?= e(setting('region','Southeast Michigan')) ?> · Last frost <?= e(setting_date_label('average_last_frost','05-05')) ?></p></div><div class="no-print"><a class="btn btn-success" href="<?= e(url('seeds/create')) ?>">Add Seed</a> <a class="btn btn-outline-success" href="<?= e(url('import')) ?>">Import</a></div></div>
    <form action="<?= e(url('seeds')) ?>" class="card card-body mb-4"><label class="form-label" for="dashboard-search">Dashboard Quick Search</label><div class="input-group"><input id="dashboard-search" class="form-control" name="search" placeholder="Seed number, name, variety, category, family, use, companion, or notes"><button class="btn btn-success">Search Inventory</button></div></form>
    <div class="row g-3 mb-4"><?php foreach ($counts as $label => $count): ?><div class="col-6 col-lg-3"><div class="card card-metric shadow-sm h-100"><div class="card-body"><div class="text-muted small"><?= e($label) ?></div><div class="display-6 fw-bold"><?= e($count) ?></div></div></div></div><?php endforeach; ?></div>
    <div class="card card-body"><h2 class="h4">Quick Actions</h2><div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-success" href="<?= e(url('seeds')) ?>">View All Seeds</a><a class="btn btn-outline-success" href="<?= e(url('seeds/create')) ?>">Add New Seed</a><a class="btn btn-outline-success" href="<?= e(url('calendar')) ?>">Planting Calendar</a><a class="btn btn-outline-success" href="<?= e(url('companions')) ?>">Companion Finder</a><a class="btn btn-outline-success" href="<?= e(url('import')) ?>">Import Seeds</a><a class="btn btn-outline-secondary" href="<?= e(url('export')) ?>">Export</a><a class="btn btn-outline-secondary" href="<?= e(url('print')) ?>">Print</a></div></div>
    <?php });
}

function seeds_page(): void
{
    $filters = $_GET;
    $filterErrors=seed_filter_validation_errors($filters);
    $result=$filterErrors
        ? ['rows'=>[],'total'=>0,'overall_total'=>(int)db()->query('SELECT COUNT(*) FROM seeds')->fetchColumn(),'page'=>0,'per_page'=>in_array((int)($filters['per_page']??0),inventory_page_sizes(),true)?(int)$filters['per_page']:(int)setting_choice('rows_per_page',array_map('strval',inventory_page_sizes()),'25'),'pages'=>0]
        : seed_query($filters, true);
    $seeds = $result['rows'];
    $ref = reference_data();
    render('Seed Inventory', function () use ($seeds, $ref, $filters, $result, $filterErrors) { include BASE_PATH . '/app/templates/seeds_index.php'; });
}

function seed_form_page(?int $id): void
{
    $seed = $id ? seed_find($id) : [];
    if ($id && !$seed) { http_response_code(404); render('Seed Not Found', fn() => print '<h1>Seed not found</h1>'); return; }
    if (is_post()) {
        verify_csrf();
        try {
            $saved=seed_save($id); $action=$_POST['save_action']??'save';
            if ($action==='duplicate') {
                try { $copy=duplicate_seed($saved); flash('success','Seed saved and duplicated.'); redirect('seeds/'.$copy.'/edit'); }
                catch (Throwable $e) { error_log((string)$e); flash('warning','The seed was saved, but its duplicate could not be created.'); redirect('seeds/'.$saved); }
            }
            flash('success','Seed saved.'); if ($action==='add_another') redirect('seeds/create'); redirect('seeds/'.$saved);
        } catch (PDOException $e) {
            error_log((string)$e); flash('danger','The seed could not be saved. Please review the form and try again.'); $seed=failed_seed_form_state($seed?:[],$_POST);
        } catch (RuntimeException $e) {
            flash('danger',$e->getMessage()); $seed=failed_seed_form_state($seed?:[],$_POST);
        }
    }
    $ref = reference_data();
    $allSeeds = db()->query('SELECT id, seed_number, name, variety FROM seeds ORDER BY name')->fetchAll();
    $selectedUses = is_post() ? array_map('intval', (array)($_POST['uses'] ?? [])) : ($id ? array_column(seed_uses_for($id), 'id') : []);
    $companions = is_post() ? (array)($_POST['companions'] ?? []) : ($id ? seed_companions_for($id) : []);
    render($id ? 'Edit Seed' : 'Add Seed', function () use ($seed, $ref, $allSeeds, $selectedUses, $companions, $id) { include BASE_PATH . '/app/templates/seed_form.php'; });
}

function seed_detail_page(int $id): void
{
    $seed = seed_find($id);
    if (!$seed) { http_response_code(404); render('Seed Not Found', fn() => print '<h1>Seed not found</h1>'); return; }
    $uses = seed_uses_for($id);
    $companions = seed_companions_for($id);
    $history = seed_history_for($id);
    render($seed['name'], function () use ($seed, $uses, $companions, $history) { include BASE_PATH . '/app/templates/seed_detail.php'; });
}

function calendar_group_rules(): array
{
    // Phase 3 inferred groups are centralized here. Fall Crop means a planting
    // window that includes Aug-Nov (including explicit Plantable Months); the
    // other groups use existing method, category, and medicinal flag data.
    return [
        'direct_sow'=>'Direct Sow', 'start_indoors'=>'Start Indoors', 'transplant'=>'Transplant',
        'fall_crop'=>'Fall Crop', 'flowers'=>'Flowers', 'herbs'=>'Herbs', 'medicinal'=>'Medicinal',
    ];
}

function calendar_month_in_range(int $month, mixed $start, mixed $end): bool
{
    if ($start === null || $end === null) return false;
    $start=(int)$start; $end=(int)$end;
    return $start <= $end ? $month >= $start && $month <= $end : $month >= $start || $month <= $end;
}

function calendar_general_month_matches(array $seed, int $month): bool
{
    $months=array_map('intval',array_filter(explode(',',(string)($seed['plantable_months']??''))));
    if ($months) return in_array($month,$months,true);
    return calendar_month_in_range($month,$seed['planting_start_month']??null,$seed['planting_end_month']??null);
}

function calendar_method_month_matches(array $seed, string $group, int $month): bool
{
    $methods=[
        'direct_sow'=>['direct_sow',['Direct Sow','Direct Sow or Transplant']],
        'start_indoors'=>['indoor',['Start Indoors']],
        'transplant'=>['transplant',['Transplant','Direct Sow or Transplant']],
    ];
    if (!isset($methods[$group])) return false;
    [$prefix,$methodNames]=$methods[$group];
    $start=$seed[$prefix.'_start_month']??null; $end=$seed[$prefix.'_end_month']??null;
    // A complete dedicated range is authoritative, including cross-year ranges.
    if ($start !== null && $end !== null) return calendar_month_in_range($month,$start,$end);
    // Only method-capable seeds fall back to the general window/month selection.
    return in_array($seed['planting_method']??null,$methodNames,true) && calendar_general_month_matches($seed,$month);
}

function calendar_group_matches(array $seed, string $group, int $month): bool
{
    if (in_array($group,['direct_sow','start_indoors','transplant'],true)) {
        return calendar_method_month_matches($seed,$group,$month);
    }
    $category=strtolower(trim((string)($seed['category_name']??'')));
    $months=[];
    for ($candidate=1;$candidate<=12;$candidate++) if (calendar_general_month_matches($seed,$candidate)) $months[]=$candidate;
    return match($group) {
        'fall_crop'=>(bool)array_intersect($months,[8,9,10,11]),
        'flowers'=>in_array($category,['flower','flowers'],true),
        'herbs'=>in_array($category,['herb','herbs'],true),
        'medicinal'=>!empty($seed['medicinal'])||$category==='medicinal',
        default=>true,
    };
}

function calendar_page(): void
{
    $month=(int)($_GET['month']??date('n')); if($month<1||$month>12)$month=(int)date('n');
    $rules=calendar_group_rules(); $group=(string)($_GET['group']??''); if(!isset($rules[$group]))$group='';
    $methodGroups=['direct_sow','start_indoors','transplant'];
    // All and non-method groups retain the established general-month query. Method
    // groups start from inventory so a dedicated range can include a seed even when
    // its general planting window does not overlap the selected month.
    $seeds=in_array($group,$methodGroups,true)
        ? seed_query(['sort'=>'planting_start_month'])
        : seed_query(['plantable_month'=>$month,'sort'=>'planting_start_month']);
    if($group!=='')$seeds=array_values(array_filter($seeds,fn($seed)=>calendar_group_matches($seed,$group,$month)));
    render('Planting Calendar', function() use($month,$seeds,$rules,$group){ ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><div><h1>Planting Calendar</h1><p class="text-muted mb-0">Year-independent windows include explicit Plantable Months and cross-year ranges.</p></div><a class="btn btn-outline-secondary" href="<?=e(url('print?report=calendar&month='.$month))?>">Print</a></div>
    <form class="card card-body mb-3"><div class="row g-2"><div class="col-md-6"><label class="form-label" for="calendar-month">Select Month</label><select id="calendar-month" class="form-select" name="month"><?php for($m=1;$m<=12;$m++):?><option value="<?=$m?>" <?=$m===$month?'selected':''?>><?=e(month_name($m))?></option><?php endfor?></select></div><div class="col-md-6"><label class="form-label" for="calendar-group">Group / Filter</label><select id="calendar-group" class="form-select" name="group"><option value="">All plantable seeds</option><?php foreach($rules as $key=>$label):?><option value="<?=e($key)?>" <?=$group===$key?'selected':''?>><?=e($label)?></option><?php endforeach?></select></div><div class="col-12"><button class="btn btn-success">Show Calendar</button></div></div></form>
    <div class="card"><div class="card-header fw-bold"><?=e($group!==''?$rules[$group].' · ':'')?><?=e(month_name($month))?> (<?=count($seeds)?>)</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Seed Number</th><th>Seed Name</th><th>Category</th><th>Planting Method</th><th>Start Planting Date</th><th>Last Planting Date</th><th>Days to Harvest/Maturity</th><th>Notes</th><th>View Seed</th></tr></thead><tbody><?php foreach($seeds as $seed):?><tr><td><?=e($seed['seed_number'])?></td><td><?=e($seed['name'])?></td><td><?=e($seed['category_name']?:'—')?></td><td><?=e($seed['planting_method']?:'—')?></td><td><?=e(date_label($seed['planting_start_month'],$seed['planting_start_day']))?></td><td><?=e(date_label($seed['planting_end_month'],$seed['planting_end_day']))?></td><td><?=e(maturity_display($seed,'—'))?></td><td class="calendar-notes"><?=($seed['notes']??'')!==''?nl2br(e($seed['notes'])):'—'?></td><td><a class="btn btn-sm btn-outline-success" href="<?=e(url('seeds/'.$seed['id']))?>">View Seed</a></td></tr><?php endforeach;if(!$seeds):?><tr><td colspan="9" class="text-muted">No seeds match this month and group.</td></tr><?php endif?></tbody></table></div></div><?php });
}

/**
 * Relationship direction is centralized here: only mutual compatibility types
 * are symmetric. Functional roles retain the stored seed -> companion direction.
 */
function companion_relationship_direction_rules(): array
{
    return [
        'Good Companion'=>'symmetric', 'Avoid'=>'symmetric', 'Neutral'=>'symmetric',
        'Pest Deterrent'=>'directional', 'Trap Crop'=>'directional',
        'Support Plant'=>'directional', 'Pollinator Support'=>'directional',
    ];
}

function companion_finder_deduplicate(array $rows, array $directionRules): array
{
    $deduplicated=[];
    foreach($rows as $row) {
        $key=(int)$row['seed_id'].'|'.$row['relationship_type'];
        if(!isset($deduplicated[$key])) {
            $row['_notes']=[]; $row['_directions']=[]; $deduplicated[$key]=$row;
        }
        $notes=trim((string)($row['notes']??''));
        if($notes!=='') $deduplicated[$key]['_notes'][$notes]=true;
        if(($directionRules[$row['relationship_type']]??'directional')==='directional') {
            $direction=$row['source_name'].' #'.$row['source_number'].' → '.$row['target_name'].' #'.$row['target_number'];
            $deduplicated[$key]['_directions'][$direction]=true;
        }
    }
    foreach($deduplicated as &$row) {
        $notes=array_keys($row['_notes']); sort($notes,SORT_NATURAL|SORT_FLAG_CASE);
        $directions=array_keys($row['_directions']); sort($directions,SORT_NATURAL|SORT_FLAG_CASE);
        $row['notes']=$notes?implode('; ',$notes):null;
        $row['direction']=$directions?implode('; ',$directions):'Mutual';
        unset($row['_notes'],$row['_directions']);
    }
    unset($row);
    return array_values($deduplicated);
}

function companions_page(): void
{
    $q=trim((string)($_GET['q']??'')); $type=trim((string)($_GET['type']??''));
    $directionRules=companion_relationship_direction_rules(); $types=array_keys($directionRules);
    if($type!==''&&!isset($directionRules[$type]))$type='';
    $sql="SELECT cr.id relationship_id, cr.relationship_type, cr.notes,
        CASE WHEN ? <> '' AND (target.name LIKE ? OR target.variety LIKE ? OR target.seed_number LIKE ?) THEN source.id ELSE target.id END seed_id,
        CASE WHEN ? <> '' AND (target.name LIKE ? OR target.variety LIKE ? OR target.seed_number LIKE ?) THEN source.seed_number ELSE target.seed_number END seed_number,
        CASE WHEN ? <> '' AND (target.name LIKE ? OR target.variety LIKE ? OR target.seed_number LIKE ?) THEN source.name ELSE target.name END seed_name,
        CASE WHEN ? <> '' AND (target.name LIKE ? OR target.variety LIKE ? OR target.seed_number LIKE ?) THEN source_category.name ELSE target_category.name END category_name,
        source.id source_id, source.seed_number source_number, source.name source_name,
        target.id target_id, target.seed_number target_number, target.name target_name
        FROM companion_relationships cr
        JOIN seeds source ON source.id=cr.seed_id
        JOIN seeds target ON target.id=cr.companion_seed_id
        LEFT JOIN categories source_category ON source_category.id=source.category_id
        LEFT JOIN categories target_category ON target_category.id=target.category_id";
    $term='%'.$q.'%'; $params=[];
    for($i=0;$i<4;$i++) array_push($params,$q,$term,$term,$term);
    $where=[];
    if($q!=='') {
        $where[]='(source.name LIKE ? OR source.variety LIKE ? OR source.seed_number LIKE ? OR target.name LIKE ? OR target.variety LIKE ? OR target.seed_number LIKE ?)';
        array_push($params,$term,$term,$term,$term,$term,$term);
    }
    if($type!==''){$where[]='cr.relationship_type=?';$params[]=$type;}
    if($where)$sql.=' WHERE '.implode(' AND ',$where);
    $sql.=' ORDER BY cr.relationship_type, seed_name, seed_number, cr.id';
    $stmt=db()->prepare($sql);$stmt->execute($params);$rows=companion_finder_deduplicate($stmt->fetchAll(),$directionRules);
    render('Companion Finder',function()use($rows,$q,$type,$types,$directionRules){?>
    <h1>Companion Finder</h1><p class="text-muted">Search either plant in a stored relationship. Good Companion, Avoid, and Neutral are mutual; functional relationships retain and display their stored source → target direction.</p><form class="card card-body mb-3"><div class="row g-2"><div class="col-md-8"><label class="form-label" for="companion-search">Search plant name, variety, or seed number</label><input id="companion-search" class="form-control" name="q" value="<?=e($q)?>"></div><div class="col-md-4"><label class="form-label" for="companion-type">Relationship</label><select id="companion-type" class="form-select" name="type"><option value="">All relationship types</option><?php foreach($types as $t):?><option <?=$type===$t?'selected':''?>><?=e($t)?></option><?php endforeach?></select></div><div class="col-12"><button class="btn btn-success">Search</button> <a class="btn btn-outline-secondary" href="<?=e(url('companions'))?>">Clear</a></div></div></form>
    <?php $current=null;foreach($rows as $row):if($current!==$row['relationship_type']):if($current!==null):?></tbody></table></div></section><?php endif;$current=$row['relationship_type'];$badge=$current==='Avoid'?'text-bg-danger':($current==='Good Companion'?'text-bg-success':'text-bg-secondary');?><section class="card mb-3"><div class="card-header"><span class="badge <?=e($badge)?>"><?=e($current)?></span></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Seed Number</th><th>Seed Name</th><th>Category</th><th>Relationship</th><th>Direction</th><th>Reason / Notes</th><th>View Seed</th></tr></thead><tbody><?php endif;?><tr><td><?=e($row['seed_number'])?></td><td><?=e($row['seed_name'])?></td><td><?=e($row['category_name']?:'—')?></td><td><?=e($row['relationship_type'])?></td><td><?=e($row['direction'])?></td><td><?=e($row['notes']?:'—')?></td><td><a class="btn btn-sm btn-outline-success" href="<?=e(url('seeds/'.$row['seed_id']))?>">View Seed</a></td></tr><?php endforeach;if($current!==null):?></tbody></table></div></section><?php else:?><div class="alert alert-info">No companion relationships match. Add them from a seed edit page.</div><?php endif;});
}

function settings_page(): void
{
    require_owner();
    if (is_post()) {
        verify_csrf();
        $keys=['zone','zip','region','average_last_frost','average_first_frost','garden_notes','display_exact_dates','display_plantable_months','seed_number_order','default_inventory_sort','rows_per_page'];
        $values=[]; foreach($keys as $key) $values[$key]=trim((string)($_POST[$key]??''));
        $errors=[];
        if(!preg_match('/^\d{1,2}[A-Z]?$/i',$values['zone'])) $errors[]='Growing Zone must be a number optionally followed by a letter (for example, 6B).';
        if(!preg_match('/^\d{5}(-\d{4})?$/',$values['zip'])) $errors[]='ZIP must be a valid US ZIP code.';
        if($values['region']===''||mb_strlen($values['region'])>190) $errors[]='Region is required and must be 190 characters or fewer.';
        if(mb_strlen($values['garden_notes'])>10000) $errors[]='Garden Notes must be 10,000 characters or fewer.';
        foreach(['average_last_frost','average_first_frost'] as $key) if(!valid_mmdd($values[$key])) $errors[]=ucwords(str_replace('_',' ',$key)).' must be a valid reusable MM-DD date.';
        foreach(['display_exact_dates','display_plantable_months'] as $key) if(!in_array($values[$key],['0','1'],true)) $errors[]='Display choices must be Show or Hide.';
        if(!in_array($values['seed_number_order'],['natural','lexicographic'],true)) $errors[]='Choose a supported Seed Number ordering.';
        if(!in_array($values['default_inventory_sort'],inventory_sort_options(),true)) $errors[]='Choose a supported default inventory sort.';
        if(!in_array($values['rows_per_page'],array_map('strval',inventory_page_sizes()),true)) $errors[]='Choose a supported Rows Per Page value.';
        if($errors) flash('danger',implode(' ',$errors));
        else { foreach($values as $key=>$value) save_setting($key,$value); flash('success','Settings saved.'); redirect('settings'); }
    }
    render('Settings',function(){
        $sortLabels=['Seed Number','Name','Variety','Category','Plant Family','Plant Type','Planting Method','Germination','Maturity','Seed Source','Start Date','Last Date','Packet Year','Storage Box','Status']; ?>
    <div class="d-flex flex-wrap justify-content-between gap-2"><h1>Settings</h1><a class="btn btn-outline-danger align-self-start" href="<?=e(url('backup'))?>">Database Backup &amp; Restore</a></div>
    <form method="post"><?=csrf_field()?><div class="row g-3"><div class="col-lg-6"><section class="card card-body h-100"><h2 class="h4">Garden Settings</h2><?php foreach(['zone'=>['Growing Zone','6B'],'zip'=>['ZIP Code','48239'],'region'=>['Region','Southeast Michigan'],'average_last_frost'=>['Average Last Frost (MM-DD)','05-05'],'average_first_frost'=>['Average First Frost (MM-DD)','10-15']] as $key=>[$label,$default]):?><div class="mb-3"><label class="form-label" for="setting-<?=e($key)?>"><?=e($label)?></label><input id="setting-<?=e($key)?>" class="form-control" name="<?=e($key)?>" value="<?=e(setting($key,$default))?>" required></div><?php endforeach?><label class="form-label" for="garden-notes">Garden Notes</label><textarea id="garden-notes" class="form-control" name="garden_notes" rows="5" maxlength="10000"><?=e(setting('garden_notes'))?></textarea></section></div>
    <div class="col-lg-6"><section class="card card-body h-100"><h2 class="h4">Display Settings</h2><?php foreach(['display_exact_dates'=>['Exact-date display',['1'=>'Show month and day','0'=>'Show month only'],'1'],'display_plantable_months'=>['Plantable Months display',['1'=>'Show','0'=>'Hide'],'1'],'seed_number_order'=>['Seed Number ordering',['natural'=>'Natural numeric order','lexicographic'=>'Exact text order'],'natural']] as $key=>[$label,$options,$default]):?><div class="mb-3"><label class="form-label" for="setting-<?=e($key)?>"><?=e($label)?></label><select id="setting-<?=e($key)?>" class="form-select" name="<?=e($key)?>"><?php foreach($options as $value=>$text):?><option value="<?=e($value)?>" <?=setting_choice($key,array_keys($options),$default)===$value?'selected':''?>><?=e($text)?></option><?php endforeach?></select></div><?php endforeach?><div class="mb-3"><label class="form-label" for="default-sort">Default inventory sort</label><select id="default-sort" class="form-select" name="default_inventory_sort"><?php foreach(array_combine(inventory_sort_options(),$sortLabels) as $value=>$text):?><option value="<?=e($value)?>" <?=setting_choice('default_inventory_sort',inventory_sort_options(),'seed_number')===$value?'selected':''?>><?=e($text)?></option><?php endforeach?></select></div><div class="mb-3"><label class="form-label" for="rows-per-page">Rows Per Page</label><select id="rows-per-page" class="form-select" name="rows_per_page"><?php foreach(inventory_page_sizes() as $size):?><option value="<?=$size?>" <?=setting_choice('rows_per_page',array_map('strval',inventory_page_sizes()),'25')===(string)$size?'selected':''?>><?=$size?></option><?php endforeach?></select></div><p class="small text-muted">Valid URL sort and page-size choices continue to override these defaults.</p></section></div></div><button class="btn btn-success mt-3">Save Settings</button></form><?php
    });
}

function storage_page(): void
{
    require_owner();
    if(is_post()) {
        verify_csrf();
        try {
            $seedId=nullable_int($_POST['seed_id']??null);
            if(!$seedId||!record_exists('seeds',$seedId)) throw new RuntimeException('Choose an existing seed record.');
            $limits=['storage_box'=>120,'container'=>120,'envelope'=>120,'row_label'=>80,'slot'=>80,'location_notes'=>10000];
            foreach($limits as $key=>$limit) if(mb_strlen(trim((string)($_POST[$key]??'')))>$limit) throw new RuntimeException(ucwords(str_replace(['_','location notes'],[' ','Storage Notes'],$key))." is too long.");
            $before=seed_find($seedId);
            db()->beginTransaction();
            try {
                save_location($seedId);
                $changes=storage_history_changes($before?:[],seed_find($seedId)?:[]);
                if($changes) log_history($seedId,'updated',$changes);
                db()->commit();
            } catch(Throwable $e) { db()->rollBack(); throw $e; }
            flash('success','Storage location saved. Seed Number was not changed.');
        } catch(RuntimeException $e) { flash('danger',$e->getMessage()); }
        redirect('manage/storage');
    }
    $rows=db()->query('SELECT s.id,s.seed_number,s.name,s.variety,l.storage_box,l.container,l.envelope,l.row_label,l.slot,l.notes AS location_notes FROM seeds s LEFT JOIN seed_locations l ON l.seed_id=s.id ORDER BY s.name,s.variety,s.id')->fetchAll();
    render('Manage Storage Locations',function()use($rows){ ?>
    <h1>Manage Storage Locations</h1><p class="text-muted">Edit each seed record’s physical Storage Box, Container, Envelope, Row, Slot, and Notes. Seed Number is shown only to identify the seed and is never treated as a storage field.</p>
    <?php if(!$rows):?><div class="alert alert-info">There are no seed records yet. <a href="<?=e(url('seeds/create'))?>">Add a seed</a> before assigning a location.</div><?php else:?><div class="accordion" id="storage-list"><?php foreach($rows as $i=>$row):?><div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button <?=$i?'collapsed':''?>" type="button" data-bs-toggle="collapse" data-bs-target="#storage-<?=$row['id']?>"><strong><?=e($row['name'])?></strong>&nbsp;<?=e($row['variety']?'— '.$row['variety']:'')?> <span class="ms-2 text-muted">Seed # <?=e($row['seed_number'])?></span></button></h2><div id="storage-<?=$row['id']?>" class="accordion-collapse collapse <?=$i===0?'show':''?>" data-bs-parent="#storage-list"><div class="accordion-body"><form method="post" class="row g-3"><?=csrf_field()?><input type="hidden" name="seed_id" value="<?=$row['id']?>"><?php foreach(['storage_box'=>['Storage Box',120],'container'=>['Container',120],'envelope'=>['Envelope',120],'row_label'=>['Row',80],'slot'=>['Slot',80]] as $key=>[$label,$max]):?><div class="col-sm-6 col-lg"><label class="form-label" for="<?=$key?>-<?=$row['id']?>"><?=e($label)?></label><input id="<?=$key?>-<?=$row['id']?>" class="form-control" name="<?=$key?>" maxlength="<?=$max?>" value="<?=e($row[$key])?>"></div><?php endforeach?><div class="col-12"><label class="form-label" for="location-notes-<?=$row['id']?>">Storage Notes</label><textarea id="location-notes-<?=$row['id']?>" class="form-control" name="location_notes" maxlength="10000"><?=e($row['location_notes'])?></textarea></div><div class="col-12"><button class="btn btn-success">Save Location</button></div></form></div></div></div><?php endforeach?></div><?php endif?><?php
    });
}

function manage_page(string $section): void
{
    require_owner();
    $map = ['categories'=>['categories','Category'], 'families'=>['plant_families','Plant Family'], 'uses'=>['uses','Use'], 'statuses'=>['statuses','Status']];
    [$table,$label] = $map[$section];
    if (is_post()) {
        verify_csrf();
        try {
            if (isset($_POST['delete_id'])) {
                $id=(int)$_POST['delete_id'];
                if(!record_exists($table,$id)) throw new RuntimeException("$label was not found.");
                $reference=match($section){'categories'=>'SELECT COUNT(*) FROM seeds WHERE category_id=?','families'=>'SELECT COUNT(*) FROM seeds WHERE plant_family_id=?','statuses'=>'SELECT COUNT(*) FROM seeds WHERE status_id=?','uses'=>'SELECT COUNT(*) FROM seed_uses WHERE use_id=?'};
                $check=db()->prepare($reference); $check->execute([$id]); $count=(int)$check->fetchColumn();
                if($count>0) throw new RuntimeException("$label is used by $count seed record".($count===1?'':'s').". Reassign those seeds before deleting it.");
                db()->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);
                flash('success', "$label deleted.");
            } else {
                $id = nullable_int($_POST['id'] ?? null);
                $name=trim((string)($_POST['name'] ?? ''));
                $description=trim((string)($_POST['description'] ?? '')) ?: null;
                if ($name === '') { throw new RuntimeException("$label name is required."); }
                $nameLimit=match($section){'categories'=>100,'families','uses'=>120,'statuses'=>80};
                if (mb_strlen($name)>$nameLimit) throw new RuntimeException("$label name must be $nameLimit characters or fewer.");
                if ($id&&!record_exists($table,$id)) throw new RuntimeException("$label was not found.");
                if ($section==='statuses') {
                    if ($id) { db()->prepare('UPDATE statuses SET name=?, is_active=? WHERE id=?')->execute([$name, isset($_POST['is_active'])?1:0, $id]); }
                    else { db()->prepare('INSERT INTO statuses (name,is_active) VALUES (?,?)')->execute([$name, isset($_POST['is_active'])?1:0]); }
                } else {
                    if ($id) { db()->prepare("UPDATE $table SET name=?, description=? WHERE id=?")->execute([$name,$description,$id]); }
                    else { db()->prepare("INSERT INTO $table (name,description) VALUES (?,?)")->execute([$name,$description]); }
                }
                flash('success', "$label saved.");
            }
        } catch (PDOException $e) {
            flash('danger', "Could not change $label. It may still be referenced by seeds or another record may already use that name.");
        } catch (RuntimeException $e) {
            flash('danger', $e->getMessage());
        }
        redirect('manage/'.$section);
    }
    $rows = db()->query("SELECT * FROM $table ORDER BY name")->fetchAll();
    render('Manage ' . $label . 's', function () use ($rows,$label,$section) { ?>
    <h1>Manage <?= e($label) ?>s</h1><div class="row g-3"><div class="col-lg-4"><form method="post" class="card card-body"><?= csrf_field() ?><h2 class="h5">Add <?= e($label) ?></h2><div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required></div><?php if($section==='statuses'): ?><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" checked><label class="form-check-label">Active</label></div><?php else: ?><div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description"></textarea></div><?php endif; ?><button class="btn btn-success">Save</button></form></div><div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>Name</th><th>Details</th><th class="text-end">Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td colspan="3"><form method="post" class="row g-2 align-items-center"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($r['id']) ?>"><div class="col-md-3"><input class="form-control" name="name" value="<?= e($r['name']) ?>" required></div><?php if($section==='statuses'): ?><div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" <?= !empty($r['is_active'])?'checked':'' ?>><label class="form-check-label">Active</label></div></div><?php else: ?><div class="col-md-5"><input class="form-control" name="description" value="<?= e($r['description'] ?? '') ?>"></div><?php endif; ?><div class="col-md text-end"><button class="btn btn-sm btn-outline-success">Update</button></form><form method="post" data-confirm="Delete this item?" class="d-inline"><?= csrf_field() ?><input type="hidden" name="delete_id" value="<?= e($r['id']) ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></div></td></tr><?php endforeach; ?><?php if(!$rows):?><tr><td colspan="3" class="text-muted">No values yet. Add the first value using this form.</td></tr><?php endif?></tbody></table></div></div></div></div>
    <?php });
}
