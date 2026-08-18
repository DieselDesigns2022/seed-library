<?php
declare(strict_types=1);

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

/** Return the 24 half-month cells occupied by a planting range. */
function calendar_range_segments(mixed $startMonth, mixed $startDay, mixed $endMonth, mixed $endDay): array
{
    if (!calendar_valid_month($startMonth) || !calendar_valid_month($endMonth)) return [];
    $start=calendar_segment_number((int)$startMonth,$startDay,false);
    $end=calendar_segment_number((int)$endMonth,$endDay,true);
    $segments=[];
    for ($segment=1;$segment<=24;$segment++) {
        if ($start <= $end ? $segment >= $start && $segment <= $end : $segment >= $start || $segment <= $end) $segments[]=$segment;
    }
    return $segments;
}

function calendar_valid_month(mixed $month): bool
{
    return $month !== null && (int)$month >= 1 && (int)$month <= 12;
}

function calendar_segment_number(int $month, mixed $day, bool $rangeEnd): int
{
    // Month-only starts and ends deliberately cover both halves of that month.
    $late=$day === null || $day === '' ? $rangeEnd : (int)$day >= 16;
    return (($month-1)*2)+($late?2:1);
}

/** Method-specific ranges use the same dedicated-range/general-window fallback as the table filter. */
function calendar_activity_segments(array $seed, string $group): array
{
    $prefixes=['start_indoors'=>'indoor','direct_sow'=>'direct_sow','transplant'=>'transplant'];
    if (!isset($prefixes[$group])) return [];
    $prefix=$prefixes[$group];
    $startMonth=$seed[$prefix.'_start_month']??null; $endMonth=$seed[$prefix.'_end_month']??null;
    if (calendar_valid_month($startMonth) && calendar_valid_month($endMonth)) {
        return calendar_range_segments($startMonth,$seed[$prefix.'_start_day']??null,$endMonth,$seed[$prefix.'_end_day']??null);
    }
    $methodNames=[
        'direct_sow'=>['Direct Sow','Direct Sow or Transplant'],
        'start_indoors'=>['Start Indoors'],
        'transplant'=>['Transplant','Direct Sow or Transplant'],
    ];
    if (!in_array($seed['planting_method']??null,$methodNames[$group],true)) return [];
    $months=array_values(array_unique(array_map('intval',array_filter(explode(',',(string)($seed['plantable_months']??''))))));
    if ($months) {
        $segments=[];
        foreach ($months as $month) if ($month>=1 && $month<=12) array_push($segments,$month*2-1,$month*2);
        sort($segments); return $segments;
    }
    return calendar_range_segments($seed['planting_start_month']??null,$seed['planting_start_day']??null,$seed['planting_end_month']??null,$seed['planting_end_day']??null);
}

/** Derive maturity only from an exact, meaningful outdoor start plus stored maturity days. */
function calendar_harvest_segments(array $seed): array
{
    $min=$seed['days_to_maturity_min']??$seed['days_to_maturity']??null;
    $max=$seed['days_to_maturity_max']??$seed['days_to_maturity']??null;
    if ($min===null && $max!==null) $min=$max;
    if ($max===null && $min!==null) $max=$min;
    if ($min===null || $max===null || (int)$min<0 || (int)$max<(int)$min) return [];
    $qualifier=strtolower((string)($seed['maturity_qualifier']??''));
    $candidates=str_contains($qualifier,'transplant')?['transplant']:['transplant','direct_sow'];
    foreach ($candidates as $prefix) {
        $month=$seed[$prefix.'_start_month']??null; $day=$seed[$prefix.'_start_day']??null;
        if (!calendar_valid_month($month) || $day===null || !checkdate((int)$month,(int)$day,2000)) continue;
        $base=DateTimeImmutable::createFromFormat('!Y-n-j','2000-'.(int)$month.'-'.(int)$day);
        if (!$base) continue;
        $first=$base->modify('+'.(int)$min.' days'); $last=$base->modify('+'.(int)$max.' days');
        return calendar_range_segments((int)$first->format('n'),(int)$first->format('j'),(int)$last->format('n'),(int)$last->format('j'));
    }
    return [];
}

function calendar_seed_timeline(array $seed): array
{
    return [
        'start_indoors'=>calendar_activity_segments($seed,'start_indoors'),
        'direct_sow'=>calendar_activity_segments($seed,'direct_sow'),
        'transplant'=>calendar_activity_segments($seed,'transplant'),
        'harvest'=>calendar_harvest_segments($seed),
    ];
}

/** A selected month remains a planting/action filter; harvest never qualifies a row by itself. */
function calendar_seed_matches_planting_month(array $seed, int $month): bool
{
    if ($month<1 || $month>12) return false;
    if (calendar_general_month_matches($seed,$month)) return true;
    foreach (['start_indoors','direct_sow','transplant'] as $activity) {
        $segments=calendar_activity_segments($seed,$activity);
        if (in_array($month*2-1,$segments,true) || in_array($month*2,$segments,true)) return true;
    }
    return false;
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
