<?php
declare(strict_types=1);

function seed_query(array $filters = []): array
{
    $sql = 'SELECT s.*, c.name AS category_name, pf.name AS family_name, st.name AS status_name, l.storage_box, l.container, l.envelope, l.row_label, l.slot
            FROM seeds s
            LEFT JOIN categories c ON c.id = s.category_id
            LEFT JOIN plant_families pf ON pf.id = s.plant_family_id
            LEFT JOIN statuses st ON st.id = s.status_id
            LEFT JOIN seed_locations l ON l.seed_id = s.id';
    $where = [];
    $params = [];
    if (($filters['search'] ?? '') !== '') {
        $where[] = '(s.seed_number LIKE ? OR s.name LIKE ? OR s.variety LIKE ? OR s.notes LIKE ?)';
        $term = '%' . $filters['search'] . '%';
        array_push($params, $term, $term, $term, $term);
    }
    foreach (['category_id' => 's.category_id', 'plant_family_id' => 's.plant_family_id', 'status_id' => 's.status_id', 'packet_year' => 's.packet_year'] as $key => $column) {
        if (($filters[$key] ?? '') !== '') { $where[] = "$column = ?"; $params[] = $filters[$key]; }
    }
    foreach (['plant_type' => 's.plant_type', 'planting_method' => 's.planting_method', 'seed_source' => 's.seed_source', 'storage_box' => 'l.storage_box'] as $key => $column) {
        if (($filters[$key] ?? '') !== '') { $where[] = "$column LIKE ?"; $params[] = '%' . $filters[$key] . '%'; }
    }
    foreach (['container_friendly','pollinator_friendly','medicinal','perennial','frost_tolerant','heat_tolerant','trellis_needed'] as $flag) {
        if (($filters[$flag] ?? '') !== '') { $where[] = "s.$flag = ?"; $params[] = (int)$filters[$flag]; }
    }
    if (($filters['plantable_month'] ?? '') !== '') {
        $where[] = plantable_in_month_sql('s');
        $m = (int)$filters['plantable_month'];
        array_push($params, $m, $m, $m);
    }
    if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
    $allowedSorts = ['seed_number','name','category_name','family_name','packet_year','status_name','planting_start_month','storage_box'];
    $sort = in_array($filters['sort'] ?? '', $allowedSorts, true) ? $filters['sort'] : 'seed_number';
    $direction = strtoupper($filters['direction'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
    $sql .= " ORDER BY $sort $direction, s.name ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
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

function seed_payload(): array
{
    $data = [];
    foreach (seed_columns() as $column) {
        if (in_array($column, ['container_friendly','pollinator_friendly','medicinal','perennial','frost_tolerant','heat_tolerant','trellis_needed'], true)) {
            $data[$column] = bool_value($column);
        } elseif (str_ends_with($column, '_id') || in_array($column, ['days_to_germination_min','days_to_germination_max','days_to_maturity','planting_start_month','planting_start_day','planting_end_month','planting_end_day','indoor_start_weeks_before_frost','transplant_weeks_after_frost','succession_days','packet_year','expiration_year'], true)) {
            $data[$column] = nullable_int($_POST[$column] ?? null);
        } else {
            $value = trim((string)($_POST[$column] ?? ''));
            $data[$column] = $value === '' ? null : $value;
        }
    }
    $data['seed_number'] = trim((string)($_POST['seed_number'] ?? ''));
    $data['name'] = trim((string)($_POST['name'] ?? ''));
    return $data;
}

function validate_seed(array $data): array
{
    $errors = [];
    if (trim((string)($data['seed_number'] ?? '')) === '') { $errors[] = 'Seed number is required and is preserved exactly as entered.'; }
    if (mb_strlen((string)$data['seed_number']) > 100) { $errors[] = 'Seed number must be 100 characters or fewer.'; }
    if (trim((string)($data['name'] ?? '')) === '') { $errors[] = 'Seed name is required.'; }
    if (mb_strlen((string)$data['name']) > 190) { $errors[] = 'Seed name must be 190 characters or fewer.'; }

    foreach ([['category_id', 'categories'], ['plant_family_id', 'plant_families'], ['status_id', 'statuses']] as [$key, $table]) {
        if (!empty($data[$key]) && !record_exists($table, (int)$data[$key])) {
            $errors[] = ucfirst(str_replace('_', ' ', $key)) . ' is invalid.';
        }
    }

    $allowedMethods = ['Direct Sow','Start Indoors','Transplant','Direct Sow or Transplant'];
    if ($data['planting_method'] !== null && !in_array($data['planting_method'], $allowedMethods, true)) {
        $errors[] = 'Planting method is invalid.';
    }

    foreach ([['planting_start_month','planting_start_day', 'Planting start'], ['planting_end_month','planting_end_day', 'Planting end']] as [$m, $d, $label]) {
        if (($data[$m] && !$data[$d]) || (!$data[$m] && $data[$d])) { $errors[] = $label . ' date needs both month and day.'; }
        if ($data[$m] !== null && ($data[$m] < 1 || $data[$m] > 12)) { $errors[] = $label . ' month must be 1-12.'; }
        if ($data[$d] !== null && ($data[$d] < 1 || $data[$d] > 31)) { $errors[] = $label . ' day must be 1-31.'; }
        if ($data[$m] && $data[$d] && !valid_month_day((int)$data[$m], (int)$data[$d])) { $errors[] = $label . ' date is not a real calendar date.'; }
    }

    foreach (['days_to_germination_min','days_to_germination_max','days_to_maturity','indoor_start_weeks_before_frost','transplant_weeks_after_frost','succession_days'] as $key) {
        if ($data[$key] !== null && $data[$key] < 0) { $errors[] = ucfirst(str_replace('_', ' ', $key)) . ' cannot be negative.'; }
    }
    if ($data['days_to_germination_min'] !== null && $data['days_to_germination_max'] !== null && $data['days_to_germination_min'] > $data['days_to_germination_max']) {
        $errors[] = 'Germination minimum cannot be greater than germination maximum.';
    }
    $currentYear = (int)date('Y');
    foreach (['packet_year','expiration_year'] as $key) {
        if ($data[$key] !== null && ($data[$key] < 1900 || $data[$key] > $currentYear + 25)) {
            $errors[] = ucfirst(str_replace('_', ' ', $key)) . ' must be between 1900 and ' . ($currentYear + 25) . '.';
        }
    }
    if (!valid_date_string($data['purchase_date'])) { $errors[] = 'Purchase date must be a valid YYYY-MM-DD date.'; }
    return $errors;
}

function seed_save(?int $id): int
{
    $data = seed_payload();
    $errors = validate_seed($data);
    if ($errors) { throw new RuntimeException(implode(' ', $errors)); }
    $columns = array_keys($data);
    if ($id) {
        $assignments = implode(', ', array_map(fn($c) => "$c = ?", $columns));
        $stmt = db()->prepare("UPDATE seeds SET $assignments WHERE id = ?");
        $stmt->execute([...array_values($data), $id]);
        $seedId = $id;
        log_history($seedId, 'updated', $data);
    } else {
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = db()->prepare('INSERT INTO seeds (' . implode(', ', $columns) . ") VALUES ($placeholders)");
        $stmt->execute(array_values($data));
        $seedId = (int)db()->lastInsertId();
        log_history($seedId, 'created', $data);
    }
    save_location($seedId);
    save_seed_uses($seedId, $_POST['uses'] ?? []);
    save_companion_rows($seedId, $_POST['companions'] ?? []);
    return $seedId;
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
    $stmt = db()->prepare('INSERT IGNORE INTO seed_uses (seed_id, use_id) VALUES (?, ?)');
    foreach ($uses as $useId) { if ($useId !== '') { $stmt->execute([$seedId, (int)$useId]); } }
}

function save_companion_rows(int $seedId, array $rows): void
{
    db()->prepare('DELETE FROM companion_relationships WHERE seed_id=?')->execute([$seedId]);
    $stmt = db()->prepare('INSERT IGNORE INTO companion_relationships (seed_id, companion_seed_id, relationship_type, notes) VALUES (?, ?, ?, ?)');
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
    $seed = seed_find($id);
    if (!$seed) { throw new RuntimeException('Seed not found.'); }
    $data = array_intersect_key($seed, array_flip(seed_columns()));
    // Preserve the physical seed label exactly. The user can edit it after duplication if desired.
    $data['name'] = $data['name'] . ' (Copy)';
    $columns = array_keys($data);
    $stmt = db()->prepare('INSERT INTO seeds (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')');
    $stmt->execute(array_values($data));
    $newId = (int)db()->lastInsertId();
    $loc = ['storage_box','container','envelope','row_label','slot','location_notes'];
    $_POST = array_merge($_POST, array_intersect_key($seed, array_flip($loc)));
    save_location($newId);
    $stmt = db()->prepare('INSERT INTO seed_uses (seed_id, use_id) SELECT ?, use_id FROM seed_uses WHERE seed_id=?');
    $stmt->execute([$newId, $id]);
    $stmt = db()->prepare('INSERT INTO companion_relationships (seed_id, companion_seed_id, relationship_type, notes) SELECT ?, companion_seed_id, relationship_type, notes FROM companion_relationships WHERE seed_id=?');
    $stmt->execute([$newId, $id]);
    log_history($newId, 'duplicated', ['source_seed_id' => $id]);
    return $newId;
}

function delete_seed(int $id): void
{
    $stmt = db()->prepare('DELETE FROM seeds WHERE id=?');
    $stmt->execute([$id]);
    log_history(null, 'deleted', ['seed_id' => $id]);
}
