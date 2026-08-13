<?php
declare(strict_types=1);

function import_storage_path(): string
{
    $configured = trim((string)config('app.imports_path', ''));
    $candidates = array_values(array_unique(array_filter([$configured, BASE_PATH . '/storage/imports', sys_get_temp_dir() . '/seed-library-imports'])));
    foreach ($candidates as $directory) {
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) { continue; }
        if (is_writable($directory)) { return rtrim($directory, DIRECTORY_SEPARATOR); }
    }
    throw new RuntimeException('No writable import storage directory is available. Configure app.imports_path or grant write access to storage/imports.');
}

function default_import_column(string $header): string
{
    $key = strtolower(trim(preg_replace('/\s+/', ' ', str_replace(['_','-'], ' ', $header))));
    $map = ['seed number'=>'seed_number','seed #'=>'seed_number','seed name'=>'name','plant name'=>'name','variety'=>'variety','category'=>'category_id','plant family'=>'plant_family_id','family'=>'plant_family_id','status'=>'status_id','source'=>'seed_source','seed source'=>'seed_source','seed source/brand'=>'seed_source','packet year'=>'packet_year'];
    if (isset($map[$key])) return $map[$key];
    $candidate = strtolower(trim(preg_replace('/[^a-z0-9]+/i','_',$header),'_'));
    return in_array($candidate,seed_columns(),true) ? $candidate : '';
}

function resolve_import_lookup(string $column, mixed $value): ?int
{
    if ($value === null || trim((string)$value) === '') return null;
    $table = ['category_id'=>'categories','plant_family_id'=>'plant_families','status_id'=>'statuses'][$column] ?? null;
    if (!$table) return nullable_int($value);
    if (ctype_digit(trim((string)$value)) && record_exists($table,(int)$value)) return (int)$value;
    $stmt=db()->prepare("SELECT id FROM $table WHERE LOWER(name)=LOWER(?)"); $stmt->execute([trim((string)$value)]); $id=$stmt->fetchColumn();
    if ($id===false) throw new RuntimeException(ucwords(str_replace('_id','',$column)).' “'.trim((string)$value).'” was not found.');
    return (int)$id;
}

function parse_csv_file(string $path): array
{
    $handle = fopen($path, 'r');
    if (!$handle) { throw new RuntimeException('Unable to read upload.'); }
    $headers = fgetcsv($handle) ?: [];
    $headers = array_map(fn($h) => trim((string)$h), $headers);
    $rows = [];
    while (($row = fgetcsv($handle)) !== false) { $rows[] = array_combine($headers, array_pad($row, count($headers), '')); }
    fclose($handle);
    return ['headers'=>$headers,'rows'=>$rows];
}

function xlsx_column_index(string $cellRef): int
{
    if (!preg_match('/^([A-Z]+)/i', $cellRef, $m)) { return 0; }
    $letters = strtoupper($m[1]); $index = 0;
    for ($i = 0; $i < strlen($letters); $i++) { $index = $index * 26 + (ord($letters[$i]) - 64); }
    return $index;
}

function xlsx_cell_value(SimpleXMLElement $cell, array $shared): string
{
    $type = (string)$cell['t'];
    if ($type === 'inlineStr') { return (string)($cell->is->t ?? ''); }
    $value = (string)($cell->v ?? '');
    if ($type === 's') { return $shared[(int)$value] ?? ''; }
    if ($type === 'b') { return $value === '1' ? '1' : '0'; }
    return $value;
}

function parse_xlsx_file(string $path): array
{
    if (!class_exists('ZipArchive')) { throw new RuntimeException('XLSX import requires PHP ZipArchive.'); }
    if (!function_exists('simplexml_load_string')) { throw new RuntimeException('XLSX import requires PHP SimpleXML.'); }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) { throw new RuntimeException('Invalid XLSX file.'); }
    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml) {
        $xml = simplexml_load_string($sharedXml, 'SimpleXMLElement', LIBXML_NONET);
        if (!$xml) { throw new RuntimeException('Invalid XLSX shared strings.'); }
        foreach ($xml->si as $si) { $shared[] = (string)($si->t ?? ''); }
    }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$sheetXml) { throw new RuntimeException('Missing first worksheet.'); }
    $xml = simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NONET);
    if (!$xml) { throw new RuntimeException('Invalid XLSX worksheet.'); }
    $matrix=[];
    foreach ($xml->sheetData->row as $row) {
        $cells=[];
        foreach ($row->c as $cell) {
            $idx = xlsx_column_index((string)$cell['r']);
            if ($idx <= 0) { $idx = count($cells) + 1; }
            $cells[$idx - 1] = xlsx_cell_value($cell, $shared);
        }
        if ($cells) { ksort($cells); $matrix[] = array_values(array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells)); }
    }
    $headers=array_map(fn($h) => trim((string)$h), $matrix[0] ?? []); $rows=[];
    foreach (array_slice($matrix,1) as $row) { $rows[] = array_combine($headers, array_pad($row, count($headers), '')); }
    return ['headers'=>$headers,'rows'=>$rows];
}

function reconcile_import_perennial(array $payload, ?string $existingStatus = null): array
{
    $status=array_key_exists('perennial_status',$payload) ? $payload['perennial_status'] : $existingStatus;
    if ($status!==null && $status!=='') $payload['perennial']=$status==='Perennial'?1:0;
    return $payload;
}

function normalize_import_payload(array $payload): array
{
    $normalized = [];
    foreach (seed_columns() as $column) {
        if (!array_key_exists($column, $payload)) { continue; }
        $rawValue=$payload[$column];
        $value = $column==='seed_number' ? $rawValue : (is_string($rawValue) ? trim($rawValue) : $rawValue);
        if ($column==='plantable_months') {
            $normalized[$column]=normalize_plantable_months($value);
        } elseif (in_array($column, seed_boolean_columns(), true)) {
            $normalized[$column] = in_array(strtolower((string)$value), ['1','yes','true','y','on'], true) ? 1 : 0;
        } elseif (in_array($column, ['category_id','plant_family_id','status_id'], true)) {
            $normalized[$column] = resolve_import_lookup($column, $value);
        } elseif (in_array($column, seed_integer_columns(), true)) {
            $normalized[$column] = seed_nullable_int($value, ucwords(str_replace('_', ' ', $column)));
        } else {
            $normalized[$column] = $value === '' ? null : $value;
        }
    }
    return reconcile_import_perennial($normalized);
}

function import_page(): void
{
    if (is_post()) {
        verify_csrf();
        $step = $_POST['step'] ?? 'upload';
        if ($step === 'upload') {
            $upload = $_FILES['seed_file'] ?? null;
            if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($upload['tmp_name'])) { flash('danger','Choose a CSV or XLSX file.'); redirect('import'); }
            if (($upload['size'] ?? 0) > 10 * 1024 * 1024) { flash('danger','Import file must be 10 MB or smaller.'); redirect('import'); }
            $name = basename($upload['name']); $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv','xlsx'], true)) { flash('danger','Only CSV and XLSX are supported.'); redirect('import'); }
            try { $directory=import_storage_path(); }
            catch (RuntimeException $e) { flash('danger','Imports are temporarily unavailable because no writable upload location is available. Contact the administrator.'); redirect('import'); }
            $target=$directory.'/'.bin2hex(random_bytes(8)).'.'.$ext;
            if (!move_uploaded_file($upload['tmp_name'], $target)) { flash('danger','The uploaded file could not be saved. Please try again or contact the administrator.'); redirect('import'); }
            $_SESSION['import_file'] = $target; $_SESSION['import_ext'] = $ext;
            redirect('import?step=map');
        }
        if ($step === 'import') {
            $file = $_SESSION['import_file'] ?? ''; $ext = $_SESSION['import_ext'] ?? 'csv';
            if (!is_string($file) || !is_file($file)) { flash('danger','Import file no longer exists. Upload it again.'); redirect('import'); }
            $parsed = $ext === 'xlsx' ? parse_xlsx_file($file) : parse_csv_file($file);
            $mapping = $_POST['map'] ?? []; $duplicateAction = $_POST['duplicate_action'] ?? 'skip';
            if (!in_array($duplicateAction, ['skip','update','import','manual'], true)) { $duplicateAction = 'skip'; }
            $summary = ['imported'=>0,'updated'=>0,'skipped'=>0,'manual_review'=>0,'errors'=>[]];
            $_SESSION['manual_review_rows'] = [];
            $pdo=null;
            try {
                $pdo=db();
                $existingStmt=$pdo->prepare('SELECT id, perennial_status FROM seeds WHERE seed_number=? LIMIT 1');
                $pdo->beginTransaction();
                foreach ($parsed['rows'] as $index => $row) {
                    $payload = [];
                    foreach ($mapping as $source => $dest) { if ($dest !== '') { $payload[$dest] = $row[$source] ?? null; } }
                    $rowNumber = $index + 2;
                    try { $payload = normalize_import_payload($payload); }
                    catch (PDOException $e) { throw $e; }
                    catch (RuntimeException $e) { $summary['errors'][] = 'Row ' . $rowNumber . ': ' . $e->getMessage(); $summary['skipped']++; continue; }
                    $base = array_fill_keys(seed_columns(), null);
                    foreach (seed_boolean_columns() as $flag) { $base[$flag] = 0; }
                    $validationData = array_merge($base, $payload);
                    $errors = validate_seed($validationData);
                    if ($errors) { $summary['errors'][] = 'Row ' . $rowNumber . ': ' . implode(' ', $errors); $summary['skipped']++; continue; }
                    $existingStmt->execute([$payload['seed_number']]); $existing=$existingStmt->fetch(); $existingId=$existing['id']??false;
                    if ($existingId && $duplicateAction === 'skip') { $summary['skipped']++; continue; }
                    if ($existingId && $duplicateAction === 'manual') { $summary['manual_review']++; $_SESSION['manual_review_rows'][] = ['row'=>$rowNumber, 'seed_number'=>$payload['seed_number'], 'data'=>$payload]; continue; }
                    if ($existingId && $duplicateAction==='update') $payload=reconcile_import_perennial($payload,$existing['perennial_status']??null);
                    $columns = array_values(array_intersect(array_keys($payload), seed_columns()));
                    $data = array_map(fn($c)=>$payload[$c], $columns);
                    if ($existingId && $duplicateAction === 'update') { $assign=implode(', ', array_map(fn($c)=>"$c=?", $columns)); db()->prepare("UPDATE seeds SET $assign WHERE id=?")->execute([...$data, $existingId]); $summary['updated']++; }
                    else { $ph=implode(',', array_fill(0,count($columns),'?')); db()->prepare('INSERT INTO seeds ('.implode(',',$columns).") VALUES ($ph)")->execute($data); $summary['imported']++; }
                }
                $pdo->commit();
            } catch (PDOException $e) {
                if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
                error_log((string)$e);
                $summary['errors'][]='The import could not be completed because of a database error. No rows from this import were saved.';
                $_SESSION['import_summary']=$summary; flash('danger','Import failed. No rows were saved.'); redirect('import?step=summary');
            } catch (Throwable $e) {
                if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            $_SESSION['import_summary'] = $summary; flash('success','Import complete.'); redirect('import?step=summary');
        }
    }
    $step = $_GET['step'] ?? 'upload';
    render('Import Seeds', function () use ($step) {
        if ($step === 'map' && !empty($_SESSION['import_file'])) { $parsed = $_SESSION['import_ext'] === 'xlsx' ? parse_xlsx_file($_SESSION['import_file']) : parse_csv_file($_SESSION['import_file']); $columns = seed_columns(); ?>
        <h1>Map Columns</h1><form method="post" class="card card-body"><?= csrf_field() ?><input type="hidden" name="step" value="import"><div class="table-responsive"><table class="table"><thead><tr><th>Uploaded Column</th><th>Maps To</th><th>Preview</th></tr></thead><tbody><?php foreach($parsed['headers'] as $h): ?><tr><td><?= e($h) ?></td><td><select class="form-select" name="map[<?= e($h) ?>]"><option value="">Ignore</option><?php foreach($columns as $c): ?><option value="<?= e($c) ?>" <?= default_import_column($h)===$c?'selected':'' ?>><?= e($c) ?></option><?php endforeach; ?></select></td><td><?= e($parsed['rows'][0][$h] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></div><label class="form-label">Duplicate seed numbers</label><select class="form-select mb-3" name="duplicate_action"><option value="skip">Skip</option><option value="update">Update Existing</option><option value="import">Import Anyway</option><option value="manual">Manual Review</option></select><button class="btn btn-success">Validate & Import</button></form>
        <?php } elseif ($step === 'summary') { $s = $_SESSION['import_summary'] ?? []; $manual = $_SESSION['manual_review_rows'] ?? []; ?><h1>Import Summary</h1><div class="card card-body"><pre><?= e(json_encode($s, JSON_PRETTY_PRINT)) ?></pre><?php if($manual): ?><h2 class="h5">Manual review rows</h2><div class="table-responsive"><table class="table"><thead><tr><th>Row</th><th>Seed #</th><th>Mapped data</th></tr></thead><tbody><?php foreach($manual as $r): ?><tr><td><?= e($r['row']) ?></td><td><?= e($r['seed_number']) ?></td><td><code><?= e(json_encode($r['data'])) ?></code></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div><?php }
        else { ?><h1>Import Seeds</h1><form method="post" enctype="multipart/form-data" class="card card-body col-lg-6"><?= csrf_field() ?><input type="hidden" name="step" value="upload"><p class="text-muted">Upload CSV or XLSX, preview columns, map fields, choose duplicate handling, then import. Maximum upload size: 10 MB.</p><input class="form-control mb-3" type="file" name="seed_file" accept=".csv,.xlsx" required><button class="btn btn-success">Upload</button></form><?php }
    });
}

function export_rows_csv(array $rows): void
{
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="seed-library-export.csv"');
    $out=fopen('php://output','w'); if ($rows) fputcsv($out, array_keys($rows[0])); foreach($rows as $row) fputcsv($out,$row); fclose($out); exit;
}

function xlsx_column_name(int $index): string
{
    $name = '';
    while ($index > 0) { $index--; $name = chr(65 + ($index % 26)) . $name; $index = intdiv($index, 26); }
    return $name;
}

function export_rows_xlsx(array $rows): void
{
    if (!class_exists('ZipArchive')) { throw new RuntimeException('XLSX export requires PHP ZipArchive.'); }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="seed-library-export.xlsx"');
    $tmp=tempnam(sys_get_temp_dir(),'xlsx'); $zip=new ZipArchive(); $zip->open($tmp, ZipArchive::CREATE);
    $headers=$rows ? array_keys($rows[0]) : ['No data']; $sheet='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    $all=array_merge([$headers], array_map('array_values',$rows)); $r=1; foreach($all as $row){$sheet.='<row r="'.$r.'">'; $c=1; foreach($row as $v){$sheet.='<c r="'.xlsx_column_name($c).$r.'" t="inlineStr"><is><t>'.htmlspecialchars((string)$v, ENT_QUOTES | ENT_XML1, 'UTF-8').'</t></is></c>'; $c++;} $sheet.='</row>'; $r++;} $sheet.='</sheetData></worksheet>';
    $zip->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
    $zip->addFromString('_rels/.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml','<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Seeds" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml',$sheet); $zip->close(); readfile($tmp); unlink($tmp); exit;
}

function export_page(): void
{
    if (isset($_GET['download'])) { $rows = rows_for_export($_GET['report'] ?? 'all'); ($_GET['format'] ?? 'csv') === 'xlsx' ? export_rows_xlsx($rows) : export_rows_csv($rows); }
    render('Export Seeds', function () { $reports=['all'=>'All Seeds','filtered'=>'Filtered Results','calendar'=>'Planting Calendar','companions'=>'Companion Guide','containers'=>'Container-Friendly Plants','medicinal'=>'Medicinal Plants','pollinators'=>'Pollinator Plants','perennials'=>'Perennials','bank'=>'Seed Bank Inventory']; ?>
    <h1>Export Seeds</h1><form class="card card-body col-lg-6"><input type="hidden" name="download" value="1"><label class="form-label">Export</label><select class="form-select mb-3" name="report"><?php foreach($reports as $k=>$v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select><label class="form-label">Format</label><select class="form-select mb-3" name="format"><option value="csv">CSV</option><option value="xlsx">XLSX</option></select><button class="btn btn-success">Download</button></form><?php });
}

function rows_for_export(string $report): array
{
    return match($report) {
        'companions' => db()->query('SELECT s.name AS seed, cr.relationship_type, cs.name AS companion, cr.notes FROM companion_relationships cr JOIN seeds s ON s.id=cr.seed_id JOIN seeds cs ON cs.id=cr.companion_seed_id ORDER BY s.name')->fetchAll(),
        'containers' => seed_query(['container_friendly'=>1]), 'medicinal' => seed_query(['medicinal'=>1]), 'pollinators' => seed_query(['pollinator_friendly'=>1]), 'perennials' => seed_query(['perennial'=>1]),
        'calendar' => seed_query(['sort'=>'planting_start_month']), 'bank' => seed_query(['sort'=>'storage_box']), default => seed_query($_GET),
    };
}

function print_page(): void
{
    $report = $_GET['report'] ?? 'inventory'; $rows = rows_for_export($report === 'inventory' ? 'filtered' : $report);
    render('Print Reports', function () use ($report,$rows) { $reports=['inventory'=>'Inventory','calendar'=>'Planting Calendar','companions'=>'Companion Guide','containers'=>'Container-Friendly','medicinal'=>'Medicinal','pollinators'=>'Pollinators','perennials'=>'Perennials','bank'=>'Seed Bank']; ?><div class="no-print mb-3"><h1>Print Reports</h1><form class="row g-2 align-items-end mb-3"><div class="col-md-4"><label class="form-label">Report</label><select class="form-select" name="report"><?php foreach($reports as $key=>$label): ?><option value="<?= e($key) ?>" <?= $report===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><button class="btn btn-outline-success">Load Report</button> <button type="button" class="btn btn-success" onclick="window.print()">Print</button></div></form></div><div class="card"><div class="card-body"><h2><?= e($reports[$report] ?? ucwords(str_replace('_',' ',$report))) ?></h2><div class="table-responsive"><table class="table table-sm"><thead><tr><?php foreach(array_keys($rows[0] ?? ['message'=>'No data']) as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($rows as $r): ?><tr><?php foreach($r as $v): ?><td><?= e($v) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></div></div><?php }, ['print'=>true]);
}
