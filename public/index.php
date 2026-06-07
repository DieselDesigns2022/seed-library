<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/view.php';
require __DIR__ . '/../app/seeds.php';
require __DIR__ . '/../app/import_export.php';

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
    if (preg_match('#^seeds/(\d+)/duplicate$#', $path, $m)) { require_post(); verify_csrf(); $id = duplicate_seed((int)$m[1]); flash('success','Seed duplicated with the same physical seed number. Edit it only if you need a different label.'); redirect('seeds/' . $id . '/edit'); }
    if (preg_match('#^seeds/(\d+)/delete$#', $path, $m)) { require_post(); verify_csrf(); delete_seed((int)$m[1]); flash('success','Seed deleted.'); redirect('seeds'); }
    if ($path === 'calendar') { calendar_page(); return; }
    if ($path === 'companions') { companions_page(); return; }
    if ($path === 'import') { import_page(); return; }
    if ($path === 'export') { export_page(); return; }
    if ($path === 'print') { print_page(); return; }
    if ($path === 'settings') { settings_page(); return; }
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

function dashboard_page(): void
{
    $month = (int)date('n');
    $stats = [
        'Total Seeds' => 'SELECT COUNT(*) FROM seeds',
        'Total Vegetables' => "SELECT COUNT(*) FROM seeds s JOIN categories c ON c.id=s.category_id WHERE c.name='Vegetable'",
        'Total Herbs' => "SELECT COUNT(*) FROM seeds s JOIN categories c ON c.id=s.category_id WHERE c.name='Herb'",
        'Total Flowers' => "SELECT COUNT(*) FROM seeds s JOIN categories c ON c.id=s.category_id WHERE c.name='Flower'",
        'Total Medicinal Plants' => 'SELECT COUNT(*) FROM seeds WHERE medicinal=1',
        'Total Pollinator Plants' => 'SELECT COUNT(*) FROM seeds WHERE pollinator_friendly=1',
        'Total Container-Friendly Plants' => 'SELECT COUNT(*) FROM seeds WHERE container_friendly=1',
        'Total Perennials' => 'SELECT COUNT(*) FROM seeds WHERE perennial=1',
    ];
    $counts = [];
    foreach ($stats as $label => $sql) { $counts[$label] = (int)db()->query($sql)->fetchColumn(); }
    $stmt = db()->prepare('SELECT COUNT(*) FROM seeds s WHERE ' . plantable_in_month_sql('s'));
    $stmt->execute([$month,$month,$month]);
    $counts['Seeds Plantable This Month'] = (int)$stmt->fetchColumn();
    $stmt = db()->prepare('SELECT COUNT(*) FROM seeds WHERE planting_end_month IS NOT NULL AND planting_end_month < ? AND planting_start_month <= planting_end_month');
    $stmt->execute([$month]);
    $counts['Seeds Past Planting Window'] = (int)$stmt->fetchColumn();
    render('Dashboard', function () use ($counts) { ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h1>Dashboard</h1><p class="text-muted mb-0">Zone <?= e(setting('zone','6B')) ?> · <?= e(setting('region','Southeast Michigan')) ?> · Last frost <?= e(setting_date_label('average_last_frost','05-05')) ?></p></div><div class="no-print"><a class="btn btn-success" href="<?= e(url('seeds/create')) ?>">Add Seed</a> <a class="btn btn-outline-success" href="<?= e(url('import')) ?>">Import</a></div></div>
    <div class="row g-3"><?php foreach ($counts as $label => $count): ?><div class="col-6 col-lg-3"><div class="card card-metric shadow-sm h-100"><div class="card-body"><div class="text-muted small"><?= e($label) ?></div><div class="display-6 fw-bold"><?= e($count) ?></div></div></div></div><?php endforeach; ?></div>
    <?php });
}

function seeds_page(): void
{
    $filters = $_GET;
    $seeds = seed_query($filters);
    $ref = reference_data();
    render('Seed Inventory', function () use ($seeds, $ref, $filters) { include BASE_PATH . '/app/templates/seeds_index.php'; });
}

function seed_form_page(?int $id): void
{
    $seed = $id ? seed_find($id) : [];
    if ($id && !$seed) { http_response_code(404); render('Seed Not Found', fn() => print '<h1>Seed not found</h1>'); return; }
    if (is_post()) {
        verify_csrf();
        try { $saved = seed_save($id); flash('success', 'Seed saved.'); redirect('seeds/' . $saved); }
        catch (RuntimeException $e) { flash('danger', $e->getMessage()); $seed = array_merge($seed ?: [], $_POST); }
    }
    $ref = reference_data();
    $allSeeds = db()->query('SELECT id, seed_number, name, variety FROM seeds ORDER BY name')->fetchAll();
    $selectedUses = $id ? array_column(seed_uses_for($id), 'id') : [];
    $companions = $id ? seed_companions_for($id) : [];
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

function calendar_page(): void
{
    $month = (int)($_GET['month'] ?? date('n'));
    if ($month < 1 || $month > 12) { $month = (int)date('n'); }
    $seeds = seed_query(['plantable_month' => $month, 'sort' => 'planting_start_month']);
    render('Planting Calendar', function () use ($month, $seeds) { ?>
    <div class="d-flex justify-content-between align-items-center mb-3"><h1>Planting Calendar</h1><a class="btn btn-outline-secondary" href="<?= e(url('print?report=calendar&month=' . $month)) ?>">Print</a></div>
    <form class="card card-body mb-3"><label class="form-label">Select Month</label><select class="form-select" name="month" onchange="this.form.submit()"><?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $m===$month?'selected':'' ?>><?= e(month_name($m)) ?></option><?php endfor; ?></select></form>
    <div class="card"><div class="card-header fw-bold">Seeds plantable in <?= e(month_name($month)) ?></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Seed #</th><th>Name</th><th>Method</th><th>Window</th><th>Flags</th></tr></thead><tbody><?php foreach($seeds as $s): ?><tr><td><?= e($s['seed_number']) ?></td><td><a href="<?= e(url('seeds/'.$s['id'])) ?>"><?= e($s['name']) ?></a></td><td><?= e($s['planting_method']) ?></td><td><?= e(date_label($s['planting_start_month'],$s['planting_start_day'])) ?> – <?= e(date_label($s['planting_end_month'],$s['planting_end_day'])) ?></td><td><?= $s['frost_tolerant']?'❄️':'' ?> <?= $s['heat_tolerant']?'☀️':'' ?></td></tr><?php endforeach; if(!$seeds): ?><tr><td colspan="5" class="text-muted">No seeds found for this month.</td></tr><?php endif; ?></tbody></table></div></div>
    <?php });
}

function companions_page(): void
{
    $q = trim((string)($_GET['q'] ?? ''));
    $type = trim((string)($_GET['type'] ?? ''));
    $allowedTypes=['Good Companion','Avoid','Neutral','Pest Deterrent','Trap Crop','Support Plant','Pollinator Support'];
    if ($type !== '' && !in_array($type, $allowedTypes, true)) { $type = ''; }
    $sql = 'SELECT cr.relationship_type, cr.notes, s.name AS seed_name, s.seed_number AS seed_number, cs.name AS companion_name, cs.seed_number AS companion_number, s.id AS seed_id, cs.id AS companion_id FROM companion_relationships cr JOIN seeds s ON s.id=cr.seed_id JOIN seeds cs ON cs.id=cr.companion_seed_id';
    $where=[]; $params=[];
    if ($q !== '') { $where[]='(s.name LIKE ? OR cs.name LIKE ? OR s.seed_number LIKE ? OR cs.seed_number LIKE ?)'; $term="%$q%"; array_push($params,$term,$term,$term,$term); }
    if ($type !== '') { $where[]='cr.relationship_type=?'; $params[]=$type; }
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY s.name, cr.relationship_type, cs.name';
    $stmt=db()->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
    render('Companion Finder', function () use ($rows,$q,$type,$allowedTypes) { ?>
    <h1>Companion Finder</h1><form class="card card-body mb-3"><div class="row g-2"><div class="col-md-8"><label class="form-label">Search plants</label><input class="form-control" name="q" value="<?= e($q) ?>"></div><div class="col-md-4"><label class="form-label">Relationship</label><select class="form-select" name="type"><option value="">All</option><?php foreach($allowedTypes as $t): ?><option <?= $type===$t?'selected':'' ?>><?= e($t) ?></option><?php endforeach; ?></select></div><div class="col-12"><button class="btn btn-success">Search</button></div></div></form>
    <div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Plant</th><th>Relationship</th><th>Companion</th><th>Notes</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><a href="<?= e(url('seeds/'.$r['seed_id'])) ?>"><?= e($r['seed_name']) ?></a></td><td><span class="badge bg-success"><?= e($r['relationship_type']) ?></span></td><td><a href="<?= e(url('seeds/'.$r['companion_id'])) ?>"><?= e($r['companion_name']) ?></a></td><td><?= e($r['notes']) ?></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="4" class="text-muted">No companion records found. Add relationships from a seed edit page.</td></tr><?php endif; ?></tbody></table></div></div>
    <?php });
}

function settings_page(): void
{
    if (is_post()) {
        verify_csrf();
        $values = [];
        foreach(['zone','zip','region','average_last_frost','average_first_frost'] as $key) { $values[$key] = trim((string)($_POST[$key] ?? '')); }
        $errors = [];
        if ($values['zone'] === '') { $errors[] = 'Zone is required.'; }
        if (!preg_match('/^\d{5}(-\d{4})?$/', $values['zip'])) { $errors[] = 'ZIP must be a valid US ZIP code.'; }
        if ($values['region'] === '') { $errors[] = 'Region is required.'; }
        foreach (['average_last_frost','average_first_frost'] as $key) { if (!valid_mmdd($values[$key])) { $errors[] = str_replace('_', ' ', $key) . ' must be a valid MM-DD date.'; } }
        if ($errors) { flash('danger', implode(' ', $errors)); }
        else { foreach($values as $key => $value) save_setting($key, $value); flash('success','Settings saved.'); redirect('settings'); }
    }
    render('Settings', function () { ?>
    <h1>Settings</h1><form method="post" class="card card-body col-lg-6"><?= csrf_field() ?><?php foreach(['zone'=>'Zone','zip'=>'ZIP','region'=>'Region','average_last_frost'=>'Average Last Frost (MM-DD)','average_first_frost'=>'Average First Frost (MM-DD)'] as $key=>$label): ?><div class="mb-3"><label class="form-label"><?= e($label) ?></label><input class="form-control" name="<?= e($key) ?>" value="<?= e(setting($key)) ?>" required></div><?php endforeach; ?><button class="btn btn-success">Save Settings</button></form>
    <?php });
}

function manage_page(string $section): void
{
    $map = ['categories'=>['categories','Category'], 'families'=>['plant_families','Plant Family'], 'uses'=>['uses','Use'], 'statuses'=>['statuses','Status']];
    [$table,$label] = $map[$section];
    if (is_post()) {
        verify_csrf();
        try {
            if (isset($_POST['delete_id'])) {
                db()->prepare("DELETE FROM $table WHERE id=?")->execute([(int)$_POST['delete_id']]);
                flash('success', "$label deleted.");
            } else {
                $id = nullable_int($_POST['id'] ?? null);
                $name=trim((string)($_POST['name'] ?? ''));
                $description=trim((string)($_POST['description'] ?? '')) ?: null;
                if ($name === '') { throw new RuntimeException("$label name is required."); }
                if ($section==='statuses') {
                    if ($id) { db()->prepare('UPDATE statuses SET name=?, is_active=? WHERE id=?')->execute([$name, isset($_POST['is_active'])?1:0, $id]); }
                    else { db()->prepare('INSERT INTO statuses (name,is_active) VALUES (?,?) ON DUPLICATE KEY UPDATE is_active=VALUES(is_active)')->execute([$name, isset($_POST['is_active'])?1:0]); }
                } else {
                    if ($id) { db()->prepare("UPDATE $table SET name=?, description=? WHERE id=?")->execute([$name,$description,$id]); }
                    else { db()->prepare("INSERT INTO $table (name,description) VALUES (?,?) ON DUPLICATE KEY UPDATE description=VALUES(description)")->execute([$name,$description]); }
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
    <h1>Manage <?= e($label) ?>s</h1><div class="row g-3"><div class="col-lg-4"><form method="post" class="card card-body"><?= csrf_field() ?><h2 class="h5">Add <?= e($label) ?></h2><div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required></div><?php if($section==='statuses'): ?><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" checked><label class="form-check-label">Active</label></div><?php else: ?><div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description"></textarea></div><?php endif; ?><button class="btn btn-success">Save</button></form></div><div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>Name</th><th>Details</th><th class="text-end">Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td colspan="3"><form method="post" class="row g-2 align-items-center"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($r['id']) ?>"><div class="col-md-3"><input class="form-control" name="name" value="<?= e($r['name']) ?>" required></div><?php if($section==='statuses'): ?><div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" <?= !empty($r['is_active'])?'checked':'' ?>><label class="form-check-label">Active</label></div></div><?php else: ?><div class="col-md-5"><input class="form-control" name="description" value="<?= e($r['description'] ?? '') ?>"></div><?php endif; ?><div class="col-md text-end"><button class="btn btn-sm btn-outline-success">Update</button></form><form method="post" data-confirm="Delete this item?" class="d-inline"><?= csrf_field() ?><input type="hidden" name="delete_id" value="<?= e($r['id']) ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></div></td></tr><?php endforeach; ?></tbody></table></div></div></div></div>
    <?php });
}
