<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

const RESEARCH_START = '[[RESEARCH ENRICHMENT START]]';
const RESEARCH_END = '[[RESEARCH ENRICHMENT END]]';

function fail_cli(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function normalize_note_block(?string $existing, array $record): string
{
    $existing = trim((string)$existing);
    $pattern = '/\n?' . preg_quote(RESEARCH_START, '/') . '.*?' . preg_quote(RESEARCH_END, '/') . '\n?/s';
    $existing = trim((string)preg_replace($pattern, "\n", $existing));

    $lines = [RESEARCH_START];
    $noteMap = [
        'zone_6b_notes' => 'Zone 6B',
        'germination_notes' => 'Germination',
        'harvest_notes' => 'Harvest',
        'seed_saving_notes' => 'Seed Saving',
        'special_care_notes' => 'Special Care',
    ];
    foreach ($noteMap as $key => $label) {
        $value = trim((string)($record[$key] ?? ''));
        if ($value !== '') {
            $lines[] = $label . ': ' . $value;
        }
    }

    $companions = $record['companions'] ?? [];
    if (is_array($companions) && $companions) {
        foreach ($companions as $companion) {
            if (!is_array($companion)) continue;
            $plant = trim((string)($companion['plant'] ?? ''));
            $variety = trim((string)($companion['variety'] ?? ''));
            $type = trim((string)($companion['relationship_type'] ?? ''));
            $notes = trim((string)($companion['notes'] ?? ''));
            if ($plant === '' || $type === '') continue;
            $label = 'Companion: ' . $plant . ($variety !== '' ? ' — ' . $variety : '') . ' [' . $type . ']';
            if ($notes !== '') $label .= ' — ' . $notes;
            $lines[] = $label;
        }
    }

    $sources = $record['sources'] ?? [];
    if (is_array($sources) && $sources) {
        foreach ($sources as $source) {
            if (!is_array($source)) continue;
            $name = trim((string)($source['source_name'] ?? ''));
            $url = trim((string)($source['url'] ?? ''));
            $supports = trim((string)($source['supports'] ?? ''));
            if ($name === '' && $url === '') continue;
            $line = 'Research Source: ' . ($name !== '' ? $name : $url);
            if ($url !== '' && $url !== $name) $line .= ' — ' . $url;
            if ($supports !== '') $line .= ' — ' . $supports;
            $lines[] = $line;
        }
    }

    $lines[] = RESEARCH_END;
    $block = implode("\n", $lines);
    return trim($existing === '' ? $block : $existing . "\n\n" . $block);
}

function category_id(PDO $pdo, string $name): int
{
    static $cache = [];
    $key = mb_strtolower(trim($name));
    if (isset($cache[$key])) return $cache[$key];
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name)=LOWER(?)');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id === false) fail_cli("Category not found: {$name}");
    return $cache[$key] = (int)$id;
}

function family_id(PDO $pdo, string $name): int
{
    static $cache = [];
    $key = mb_strtolower(trim($name));
    if (isset($cache[$key])) return $cache[$key];

    $stmt = $pdo->prepare('SELECT id FROM plant_families WHERE LOWER(name)=LOWER(?)');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id === false) {
        $insert = $pdo->prepare('INSERT INTO plant_families (name) VALUES (?)');
        $insert->execute([$name]);
        $id = (int)$pdo->lastInsertId();
    }
    return $cache[$key] = (int)$id;
}

function resolve_seed_by_number(PDO $pdo, string $seedNumber): array
{
    $stmt = $pdo->prepare('SELECT id, seed_number, name, variety, notes FROM seeds WHERE seed_number = ? ORDER BY id');
    $stmt->execute([$seedNumber]);
    $rows = $stmt->fetchAll();
    if (count($rows) !== 1) {
        fail_cli("Seed Number {$seedNumber} matched " . count($rows) . ' records; expected exactly 1.');
    }
    return $rows[0];
}

function resolve_companion(PDO $pdo, array $allRecords, array $companion): ?int
{
    $plant = trim((string)($companion['plant'] ?? ''));
    $variety = trim((string)($companion['variety'] ?? ''));
    if ($plant === '') return null;

    $batchMatches = [];
    foreach ($allRecords as $candidate) {
        if (strcasecmp(trim((string)($candidate['seed_name'] ?? '')), $plant) !== 0) continue;
        $candidateVariety = trim((string)($candidate['variety'] ?? ''));
        if ($variety !== '' && strcasecmp($candidateVariety, $variety) !== 0) continue;
        $batchMatches[] = (string)$candidate['seed_number'];
    }
    if (count($batchMatches) === 1) {
        return (int)resolve_seed_by_number($pdo, $batchMatches[0])['id'];
    }

    if ($variety !== '') {
        $stmt = $pdo->prepare('SELECT id FROM seeds WHERE LOWER(name)=LOWER(?) AND LOWER(COALESCE(variety, ""))=LOWER(?) ORDER BY id');
        $stmt->execute([$plant, $variety]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM seeds WHERE LOWER(name)=LOWER(?) ORDER BY id');
        $stmt->execute([$plant]);
    }
    $ids = array_map('intval', array_column($stmt->fetchAll(), 'id'));
    return count($ids) === 1 ? $ids[0] : null;
}

function update_seed(PDO $pdo, array $record, array $allRecords, array &$warnings): void
{
    $seedNumber = (string)$record['seed_number'];
    $seed = resolve_seed_by_number($pdo, $seedNumber);
    $seedId = (int)$seed['id'];

    $expectedName = trim((string)($record['seed_name'] ?? ''));
    if ($expectedName !== '' && strcasecmp(trim((string)$seed['name']), $expectedName) !== 0) {
        $warnings[] = "Seed {$seedNumber}: website name '{$seed['name']}' differs from research name '{$expectedName}'. Seed Number was used as the authoritative key.";
    }

    $updates = [];
    $set = static function(string $column, mixed $value) use (&$updates): void {
        if ($value !== null) $updates[$column] = $value;
    };

    $category = trim((string)($record['category'] ?? ''));
    if ($category !== '') $updates['category_id'] = category_id($pdo, $category);

    $family = trim((string)($record['plant_family'] ?? ''));
    if ($family !== '') $updates['plant_family_id'] = family_id($pdo, $family);

    foreach ([
        'plant_type','planting_method',
        'days_to_germination_min','days_to_germination_max',
        'days_to_maturity_min','days_to_maturity_max','maturity_qualifier',
        'planting_start_month','planting_start_day','planting_end_month','planting_end_day',
        'indoor_start_weeks_before_frost','transplant_weeks_after_frost','succession_days',
        'sun_requirements','water_requirements','soil_requirements','spacing','sowing_depth',
        'ideal_soil_temperature','row_spacing','thin_to_spacing','minimum_container_size','plant_height',
        'perennial_status'
    ] as $column) {
        $set($column, $record[$column] ?? null);
    }

    if (array_key_exists('plantable_months', $record) && is_array($record['plantable_months']) && $record['plantable_months']) {
        $updates['plantable_months'] = implode(',', array_map('intval', $record['plantable_months']));
    }

    foreach (['indoor_start','direct_sow','transplant'] as $prefix) {
        $statusKey = $prefix . '_status';
        $status = $record[$statusKey] ?? null;
        $rangeColumns = [
            $prefix . '_start_month',
            $prefix . '_start_day',
            $prefix . '_end_month',
            $prefix . '_end_day',
        ];

        if ($status !== null) {
            $updates[$statusKey] = $status;
            foreach ($rangeColumns as $column) $updates[$column] = null;
        } else {
            $hasRangeValue = false;
            foreach ($rangeColumns as $column) {
                if (($record[$column] ?? null) !== null) {
                    $hasRangeValue = true;
                    break;
                }
            }
            if ($hasRangeValue) {
                $updates[$statusKey] = null;
                foreach ($rangeColumns as $column) {
                    $updates[$column] = $record[$column] ?? null;
                }
            }
        }
    }

    $maturityMin = $record['days_to_maturity_min'] ?? null;
    $maturityMax = $record['days_to_maturity_max'] ?? null;
    if ($maturityMin !== null || $maturityMax !== null) {
        $updates['days_to_maturity'] = ($maturityMin !== null && $maturityMax !== null && (int)$maturityMin === (int)$maturityMax)
            ? (int)$maturityMin
            : null;
    }

    foreach (['container_friendly','trellis_needed','frost_tolerant','heat_tolerant','drought_tolerant','pollinator_friendly','medicinal'] as $flag) {
        if (array_key_exists($flag, $record)) $updates[$flag] = !empty($record[$flag]) ? 1 : 0;
    }
    if (array_key_exists('perennial_status', $record) && $record['perennial_status'] !== null) {
        $updates['perennial'] = $record['perennial_status'] === 'Perennial' ? 1 : 0;
    }

    $updates['notes'] = normalize_note_block($seed['notes'] ?? null, $record);

    $assignments = [];
    $params = [];
    foreach ($updates as $column => $value) {
        $assignments[] = "`{$column}` = ?";
        $params[] = $value;
    }
    $params[] = $seedId;
    $stmt = $pdo->prepare('UPDATE seeds SET ' . implode(', ', $assignments) . ' WHERE id = ?');
    $stmt->execute($params);

    $uses = $record['garden_uses'] ?? [];
    if (is_array($uses)) {
        $findUse = $pdo->prepare('SELECT id FROM uses WHERE LOWER(name)=LOWER(?)');
        $addUse = $pdo->prepare('INSERT IGNORE INTO seed_uses (seed_id, use_id) VALUES (?, ?)');
        foreach ($uses as $useName) {
            $useName = trim((string)$useName);
            if ($useName === '') continue;
            $findUse->execute([$useName]);
            $useId = $findUse->fetchColumn();
            if ($useId === false) {
                $warnings[] = "Seed {$seedNumber}: Garden Use '{$useName}' does not exist and was skipped.";
                continue;
            }
            $addUse->execute([$seedId, (int)$useId]);
        }
    }

    $companions = $record['companions'] ?? [];
    if (is_array($companions)) {
        $saveRelation = $pdo->prepare(
            'INSERT INTO companion_relationships (seed_id, companion_seed_id, relationship_type, notes)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE notes = VALUES(notes)'
        );
        foreach ($companions as $companion) {
            if (!is_array($companion)) continue;
            $type = trim((string)($companion['relationship_type'] ?? ''));
            $plant = trim((string)($companion['plant'] ?? ''));
            if ($type === '' || $plant === '') continue;
            $companionId = resolve_companion($pdo, $allRecords, $companion);
            if ($companionId === null) {
                $warnings[] = "Seed {$seedNumber}: companion '{$plant}' could not be resolved to exactly one owned seed, so the structured relationship was skipped; the research note was preserved.";
                continue;
            }
            if ($companionId === $seedId) {
                $warnings[] = "Seed {$seedNumber}: self-companion '{$plant}' was skipped.";
                continue;
            }
            $saveRelation->execute([
                $seedId,
                $companionId,
                $type,
                ($companion['notes'] ?? null) === null ? null : trim((string)$companion['notes']),
            ]);
        }
    }

    $history = $pdo->prepare('INSERT INTO seed_history (seed_id, user_id, action, changes_json) VALUES (?, NULL, ?, ?)');
    $history->execute([
        $seedId,
        'research_update',
        json_encode([
            'source' => '2026-08-14 seed research batch',
            'seed_number' => $seedNumber,
            'updated_fields' => array_keys($updates),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

$dataFile = $argv[1] ?? (__DIR__ . '/../database/data/2026-08-14-seed-research-001-025.json');
$batchArg = $argv[2] ?? 'all';

if (!is_file($dataFile)) fail_cli("Data file not found: {$dataFile}");
$decoded = json_decode((string)file_get_contents($dataFile), true);
if (!is_array($decoded)) fail_cli('Research JSON is invalid.');

$expectedNumbers = array_map('strval', range(1, 25));
$actualNumbers = array_map(static fn(array $row): string => (string)($row['seed_number'] ?? ''), $decoded);
if ($actualNumbers !== $expectedNumbers) fail_cli('Research JSON must contain Seed Numbers 1 through 25 exactly once and in order.');

$batches = [
    '1' => array_slice($decoded, 0, 10),
    '2' => array_slice($decoded, 10, 10),
    '3' => array_slice($decoded, 20, 5),
];
if ($batchArg === 'all') {
    $selected = $batches;
} elseif (isset($batches[$batchArg])) {
    $selected = [$batchArg => $batches[$batchArg]];
} else {
    fail_cli('Batch must be 1, 2, 3, or all.');
}

$pdo = db();
$warnings = [];
foreach ($selected as $batchNo => $batch) {
    $pdo->beginTransaction();
    try {
        foreach ($batch as $record) update_seed($pdo, $record, $decoded, $warnings);
        $pdo->commit();
        $numbers = array_map(static fn(array $row): string => (string)$row['seed_number'], $batch);
        echo 'PASS batch ' . $batchNo . ': Seed Numbers ' . implode(', ', $numbers) . "\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        fail_cli("Batch {$batchNo} rolled back: " . $e->getMessage());
    }
}

$count = (int)$pdo->query('SELECT COUNT(*) FROM seeds')->fetchColumn();
echo "Seed count: {$count}\n";
if ($warnings) {
    echo "WARNINGS:\n";
    foreach (array_values(array_unique($warnings)) as $warning) echo "- {$warning}\n";
}
echo "DONE\n";
