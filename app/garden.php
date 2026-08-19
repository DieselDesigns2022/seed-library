<?php
declare(strict_types=1);

function garden_methods(): array { return ['Direct Sown','Started Indoors','Transplanted','Winter Sown','Other']; }
function garden_statuses(): array { return ['Planned','Sown','Germinating','Growing','Transplanted','Harvesting','Harvested','Failed','Archived']; }
function winter_suitabilities(): array { return ['Suitable','Not Suitable','Unknown']; }
function winter_stratification_choices(): array { return ['Required','Beneficial','Not Required','Unknown']; }
function winter_hardiness_choices(): array { return ['Hardy','Tender','Unknown']; }
function winter_sowing_month_choices(): array { return [12=>'December',1=>'January',2=>'February',3=>'March']; }

function garden_validate(array $input): array
{
    $errors=[];
    $seedId=filter_var($input['seed_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!$seedId || !record_exists('seeds',(int)$seedId)) $errors[]='Choose an existing Seed Library seed.';
    if (!valid_date_string((string)($input['planted_date']??'')) || ($input['planted_date']??'')==='') $errors[]='Planted date must be a valid date.';
    if (!in_array($input['planting_method']??'',garden_methods(),true)) $errors[]='Choose a valid planting method.';
    $quantity=filter_var($input['quantity']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);
    if ($quantity===false) $errors[]='Quantity must be a whole number from 1 to 65,535.';
    $location=trim((string)($input['location']??''));
    if ($location==='' || mb_strlen($location)>190) $errors[]='Location/container/bed is required and must be 190 characters or fewer.';
    if (mb_strlen((string)($input['notes']??''))>10000) $errors[]='Notes must be 10,000 characters or fewer.';
    foreach (['actual_transplant_date'=>'Actual transplant date','actual_harvest_date'=>'Actual harvest date'] as $key=>$label) if (!valid_date_string((string)($input[$key]??''))) $errors[]=$label.' must be a valid date.';
    $planted=(string)($input['planted_date']??''); $transplant=(string)($input['actual_transplant_date']??''); $harvest=(string)($input['actual_harvest_date']??'');
    if (valid_date_string($planted) && $planted!=='' && valid_date_string($transplant) && $transplant!=='' && $transplant<$planted) $errors[]='Actual transplant date cannot be before planted date.';
    if (valid_date_string($planted) && $planted!=='' && valid_date_string($harvest) && $harvest!=='' && $harvest<$planted) $errors[]='Actual harvest date cannot be before planted date.';
    if (valid_date_string($transplant) && $transplant!=='' && valid_date_string($harvest) && $harvest!=='' && $harvest<$transplant) $errors[]='Actual harvest date cannot be before actual transplant date.';
    if (!in_array($input['status']??'',garden_statuses(),true)) $errors[]='Choose a valid planting status.';
    return $errors;
}

/** Preserve submitted strings so invalid values remain available for correction. */
function garden_failed_form_state(array $stored, array $input): array
{
    $fields=['seed_id','planted_date','planting_method','quantity','location','notes','actual_transplant_date','actual_harvest_date','status'];
    $state=$stored; foreach($fields as $field)$state[$field]=is_array($input[$field]??null)?'':(string)($input[$field]??''); return $state;
}

function garden_normalize(array $input): array
{
    return [
        'seed_id'=>(int)($input['seed_id']??0), 'planted_date'=>(string)($input['planted_date']??''),
        'planting_method'=>(string)($input['planting_method']??''), 'quantity'=>(int)($input['quantity']??0),
        'location'=>trim((string)($input['location']??'')), 'notes'=>trim((string)($input['notes']??''))?:null,
        'actual_transplant_date'=>trim((string)($input['actual_transplant_date']??''))?:null,
        'actual_harvest_date'=>trim((string)($input['actual_harvest_date']??''))?:null,
        'status'=>(string)($input['status']??''),
    ];
}

function garden_save(?int $id, array $input): int
{
    $errors=garden_validate($input); if ($errors) throw new RuntimeException(implode(' ',$errors));
    $row=garden_normalize($input); $columns=array_keys($row);
    if ($id) {
        $stmt=db()->prepare('UPDATE garden_plantings SET '.implode(', ',array_map(fn($c)=>"$c = ?",$columns)).' WHERE id = ?');
        $stmt->execute([...array_values($row),$id]);
        if (!$stmt->rowCount() && !garden_find($id)) throw new RuntimeException('Planting not found.');
        return $id;
    }
    $stmt=db()->prepare('INSERT INTO garden_plantings ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')');
    $stmt->execute(array_values($row)); return (int)db()->lastInsertId();
}

function garden_find(int $id): ?array
{
    $stmt=db()->prepare('SELECT p.*,s.seed_number,s.name AS seed_name,s.variety,s.days_to_germination_min,s.days_to_germination_max,s.days_to_maturity,s.days_to_maturity_min,s.days_to_maturity_max,s.maturity_qualifier,s.planting_method AS seed_planting_method,s.transplant_start_month,s.transplant_start_day,s.transplant_end_month,s.transplant_end_day,s.transplant_weeks_after_frost FROM garden_plantings p JOIN seeds s ON s.id=p.seed_id WHERE p.id=?');
    $stmt->execute([$id]); return $stmt->fetch()?:null;
}

function garden_all(): array
{
    return db()->query('SELECT p.*,s.seed_number,s.name AS seed_name,s.variety,s.days_to_germination_min,s.days_to_germination_max,s.days_to_maturity,s.days_to_maturity_min,s.days_to_maturity_max,s.maturity_qualifier,s.planting_method AS seed_planting_method,s.transplant_start_month,s.transplant_start_day,s.transplant_end_month,s.transplant_end_day,s.transplant_weeks_after_frost FROM garden_plantings p JOIN seeds s ON s.id=p.seed_id ORDER BY p.planted_date DESC,p.id DESC')->fetchAll();
}

function garden_date_range(string $base, mixed $min, mixed $max): ?array
{
    if (!valid_date_string($base) || $base==='') return null;
    if ($min===null && $max===null) return null; if ($min===null)$min=$max; if($max===null)$max=$min;
    $min=(int)$min;$max=(int)$max; if($min<0||$max<$min)return null;
    $date=new DateTimeImmutable($base); return [$date->modify("+$min days")->format('Y-m-d'),$date->modify("+$max days")->format('Y-m-d')];
}

function garden_expected_germination(array $p): ?array
{
    if(!in_array($p['planting_method']??'', ['Direct Sown','Started Indoors','Winter Sown'], true)) return null;
    return garden_date_range((string)($p['planted_date']??''),$p['days_to_germination_min']??null,$p['days_to_germination_max']??null);
}

function garden_maturity_basis(array $p): ?string
{
    $qualifier=strtolower(trim((string)($p['maturity_qualifier']??'')));
    if (str_contains($qualifier,'transplant')) {
        if(!empty($p['actual_transplant_date']))return (string)$p['actual_transplant_date'];
        return ($p['planting_method']??'')==='Transplanted' ? (($p['planted_date']??'')?:null) : null;
    }
    if (preg_match('/sow|seed|planting|direct/',$qualifier)) return ($p['planted_date']??'')?:null;
    // With no qualifier, only an explicitly direct-sown planting gives a defensible outdoor basis.
    if ($qualifier==='' && ($p['planting_method']??'')==='Direct Sown') return ($p['planted_date']??'')?:null;
    return null;
}

function garden_expected_harvest(array $p): ?array
{
    if (!empty($p['actual_harvest_date'])) return null;
    $basis=garden_maturity_basis($p); if(!$basis)return null;
    $single=$p['days_to_maturity']??null;
    return garden_date_range($basis,$p['days_to_maturity_min']??$single,$p['days_to_maturity_max']??$single);
}

function recurring_window_for_year(int $sm, ?int $sd, int $em, ?int $ed, int $year): ?array
{
    $sd=$sd?:1; $endYear=$sm>$em?$year+1:$year;
    $lastDay=(int)(new DateTimeImmutable(sprintf('%04d-%02d-01',$endYear,$em)))->format('t'); $ed=$ed?:$lastDay;
    if(!checkdate($sm,$sd,$year)||!checkdate($em,$ed,$endYear))return null;
    $start=sprintf('%04d-%02d-%02d',$year,$sm,$sd); $ed=min($ed,$lastDay);
    return [$start,sprintf('%04d-%02d-%02d',$endYear,$em,$ed)];
}

function garden_expected_transplant(array $p, string $lastFrost='05-05'): ?array
{
    if (!in_array($p['planting_method']??'', ['Started Indoors','Winter Sown'], true) || !empty($p['actual_transplant_date']) || !valid_date_string((string)($p['planted_date']??''))) return null;
    $year=(int)substr($p['planted_date'],0,4);
    if (!empty($p['transplant_start_month'])&&!empty($p['transplant_end_month'])) {
        $window=recurring_window_for_year((int)$p['transplant_start_month'],nullable_int($p['transplant_start_day']??null),(int)$p['transplant_end_month'],nullable_int($p['transplant_end_day']??null),$year);
        if($window && $window[1]<$p['planted_date'])$window=recurring_window_for_year((int)$p['transplant_start_month'],nullable_int($p['transplant_start_day']??null),(int)$p['transplant_end_month'],nullable_int($p['transplant_end_day']??null),$year+1);
        return $window;
    }
    if (($p['transplant_weeks_after_frost']??null)!==null && valid_mmdd($lastFrost)) {
        $base=new DateTimeImmutable($year.'-'.$lastFrost); $date=$base->modify('+'.((int)$p['transplant_weeks_after_frost']*7).' days')->format('Y-m-d');
        if($date<$p['planted_date'])$date=(new DateTimeImmutable(($year+1).'-'.$lastFrost))->modify('+'.((int)$p['transplant_weeks_after_frost']*7).' days')->format('Y-m-d'); return [$date,$date];
    }
    return null;
}

function recurring_date_countdown(string $mmdd, ?DateTimeImmutable $now=null): ?int
{
    if(!valid_mmdd($mmdd))return null; $now=$now??new DateTimeImmutable('now'); $today=$now->setTime(0,0); $year=(int)$today->format('Y');
    $target=new DateTimeImmutable($year.'-'.$mmdd); if($target<$today)$target=new DateTimeImmutable(($year+1).'-'.$mmdd);
    return max(0,(int)$today->diff($target)->format('%a'));
}

function garden_display_date(?string $date): string
{
    if(!$date || !valid_date_string($date)) return '';
    $d=new DateTimeImmutable($date);
    return $d->format('Y')===date('Y') ? $d->format('F jS') : $d->format('F jS, Y');
}

function winter_validate(array $input): array
{
    $errors=[];
    if(!in_array($input['winter_sowing_suitability']??'',winter_suitabilities(),true))$errors[]='Choose a valid winter-sowing suitability.';
    if(!in_array($input['cold_stratification']??'',winter_stratification_choices(),true))$errors[]='Choose a valid cold-stratification value.';
    if(!in_array($input['winter_hardiness']??'',winter_hardiness_choices(),true))$errors[]='Choose a valid hardiness value.';
    $months=winter_submitted_months($input['winter_sowing_months']??[],false);
    if($months===null)$errors[]='Winter-sowing months may only be the exact values 12, 1, 2, or 3.';
    $months??=[];
    if(($input['winter_sowing_suitability']??'')!=='Suitable' && $months)$errors[]='Only suitable seeds may have eligible winter-sowing months.';
    foreach(['winter_sowing_notes'=>'Notes','winter_sowing_citation'=>'Citation'] as $key=>$label)if(mb_strlen((string)($input[$key]??''))>10000)$errors[]="$label must be 10,000 characters or fewer.";
    return $errors;
}

function winter_submitted_months(mixed $submitted, bool $throw=true): ?array
{
    if(!is_array($submitted)) { if($throw)throw new RuntimeException('Winter-sowing months must be submitted as choices.'); return null; }
    $months=[];$allowed=['12'=>12,'1'=>1,'2'=>2,'3'=>3];
    foreach($submitted as $value){if(!is_int($value)&&!is_string($value)){if($throw)throw new RuntimeException('Winter-sowing month is invalid.');return null;}$key=(string)$value;if(!array_key_exists($key,$allowed)){if($throw)throw new RuntimeException('Winter-sowing months may only be the exact values 12, 1, 2, or 3.');return null;}$months[$allowed[$key]]=true;}
    $normalized=array_keys($months);$order=[12=>0,1=>1,2=>2,3=>3];usort($normalized,fn($a,$b)=>$order[$a]<=>$order[$b]);return $normalized;
}

function winter_seed_is_eligible(array $seed, int $month): bool
{
    if(($seed['winter_sowing_suitability']??'Unknown')!=='Suitable'||!isset(winter_sowing_month_choices()[$month]))return false;
    return in_array($month,array_map('intval',explode(',',(string)($seed['winter_sowing_months']??''))),true);
}

function winter_save(int $seedId,array $input): void
{
    if(!record_exists('seeds',$seedId))throw new RuntimeException('Seed not found.'); $errors=winter_validate($input);if($errors)throw new RuntimeException(implode(' ',$errors));
    $months=winter_submitted_months($input['winter_sowing_months']??[]);
    $stmt=db()->prepare('UPDATE seeds SET winter_sowing_suitability=?,winter_sowing_months=?,cold_stratification=?,winter_hardiness=?,winter_sowing_notes=?,winter_sowing_citation=? WHERE id=?');
    $stmt->execute([$input['winter_sowing_suitability'],$months?implode(',',$months):null,$input['cold_stratification'],$input['winter_hardiness'],trim((string)($input['winter_sowing_notes']??''))?:null,trim((string)($input['winter_sowing_citation']??''))?:null,$seedId]);
}
