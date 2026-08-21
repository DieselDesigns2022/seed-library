<?php
$active=array_filter($filters,fn($v)=>is_array($v)?$v!==[]:$v!==''&&$v!==null);
$exportUrl=url('export?'.http_build_query(array_merge($active,['download'=>1,'report'=>'filtered','format'=>'csv'])));
$exportXlsxUrl=url('export?'.http_build_query(array_merge($active,['download'=>1,'report'=>'filtered','format'=>'xlsx'])));
$link=function(array $changes=[]) use($active): string { return url('seeds?'.http_build_query(array_merge($active,$changes))); };
$defaultSort=setting_choice('default_inventory_sort',inventory_sort_options(),'seed_number');
$sortUrl=function(string $column) use($active,$link,$defaultSort): string { $same=($active['sort']??$defaultSort)===$column; return $link(['sort'=>$column,'direction'=>$same&&strtoupper((string)($active['direction']??'ASC'))==='ASC'?'DESC':'ASC','page'=>1]); };
$location=function(array $s): string { return implode(' · ',array_filter([$s['storage_box']??null,$s['container']??null,$s['envelope']??null,$s['row_label']??null,$s['slot']??null])); };
$months=fn(?string $value): string => plantable_months_label($value);
$flags=['container_friendly'=>'Container','pollinator_friendly'=>'Pollinator','medicinal'=>'Medicinal','perennial'=>'Perennial','frost_tolerant'=>'Frost','heat_tolerant'=>'Heat','drought_tolerant'=>'Drought','trellis_needed'=>'Trellis'];
$germination=function(array $s): string { $min=$s['days_to_germination_min']; $max=$s['days_to_germination_max']; if($min===null&&$max===null)return 'Not recorded'; if($min!==null&&$max!==null)return $min===$max?"$min days":"$min–$max days"; return ($min??$max).' days'; };
$maturity=fn(array $s): string=>maturity_display($s);
$window=function(array $s): string { $start=date_label($s['planting_start_month'],$s['planting_start_day']); $end=date_label($s['planting_end_month'],$s['planting_end_day']); return $start!==''&&$end!==''?"$start – $end":($start?:$end?:'Not recorded'); };
$showingStart=$result['total']===0?0:(($result['page']-1)*$result['per_page'])+1;
$showingEnd=$result['total']===0?0:min($result['total'],$showingStart+count($seeds)-1);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h1 class="mb-0">Seed Inventory</h1><p class="text-muted mb-0">Showing <?=e($showingStart)?>–<?=e($showingEnd)?> of <?=e($result['total'])?> seeds · Page <?=e($result['page'])?> of <?=e($result['pages'])?></p></div><?php if(!demo_read_only()):?><div class="d-flex flex-wrap gap-2"><a class="btn btn-success" href="<?=e(url('seeds/create'))?>">Add New Seed</a><?php if(!$filterErrors):?><a class="btn btn-outline-success" href="<?=e($exportUrl)?>">Filtered CSV</a><a class="btn btn-outline-success" href="<?=e($exportXlsxUrl)?>">Filtered XLSX</a><a class="btn btn-outline-secondary" href="<?=e(url('print?'.http_build_query(array_merge($active,['report'=>'inventory']))))?>">Print</a><?php endif?></div><?php endif?></div>
<?php if($filterErrors):?><div class="alert alert-danger" role="alert"><strong>Correct the planting date filters:</strong><ul class="mb-0"><?php foreach($filterErrors as $error):?><li><?=e($error)?></li><?php endforeach?></ul></div><?php endif?>
<div class="card mb-3">
  <div class="card-body">
    <form method="get" action="<?=e(url('seeds'))?>">
      <div class="mb-3">
        <label class="form-label fw-bold" for="inventory-search">Quick Search</label>
        <div class="input-group">
          <input
            id="inventory-search"
            class="form-control"
            name="search"
            value="<?=e($filters['search']??'')?>"
            placeholder="Search seed number, name, or variety"
          >
          <button class="btn btn-success" type="submit">Search</button>
          <?php if(($filters['search']??'')!==''):?>
            <a class="btn btn-outline-secondary" href="<?=e(url('seeds'))?>">Clear</a>
          <?php endif?>
        </div>
        <?php if(($filters['search']??'')!==''):?>
          <div class="small text-muted mt-2">
            Search results for <strong>“<?=e($filters['search'])?>”</strong> · <?=e($result['total'])?> seed<?=((int)$result['total']===1?'':'s')?>
          </div>
        <?php endif?>
      </div>

      <div class="row g-2 align-items-end">
        <?php foreach([
          'category_id'=>['Category',$ref['categories']],
          'status_id'=>['Status',$ref['statuses']]
        ] as $key=>[$label,$rows]): ?>
          <div class="col-6 col-lg-3">
            <label class="form-label"><?=e($label)?></label>
            <select class="form-select" name="<?=e($key)?>">
              <option value="">All</option>
              <?php foreach($rows as $r):?>
                <option value="<?=e($r['id'])?>" <?=($filters[$key]??'')==(string)$r['id']?'selected':''?>><?=e($r['name'])?></option>
              <?php endforeach?>
            </select>
          </div>
        <?php endforeach?>

        <div class="col-6 col-lg-3">
          <label class="form-label">Plantable Month</label>
          <select class="form-select" name="plantable_month">
            <option value="">All</option>
            <?php for($m=1;$m<=12;$m++):?>
              <option value="<?=$m?>" <?=($filters['plantable_month']??'')==(string)$m?'selected':''?>><?=e(month_name($m))?></option>
            <?php endfor?>
          </select>
        </div>

        <div class="col-6 col-lg-3">
          <label class="form-label">Planting Method</label>
          <select class="form-select" name="planting_method">
            <option value="">All</option>
            <?php foreach(['Direct Sow','Start Indoors','Transplant','Direct Sow or Transplant'] as $method):?>
              <option value="<?=e($method)?>" <?=($filters['planting_method']??'')===$method?'selected':''?>><?=e($method)?></option>
            <?php endforeach?>
          </select>
        </div>

        <div class="col-12 d-flex flex-wrap gap-2 mt-3">
          <button class="btn btn-success" type="submit">Apply Filters</button>
          <a class="btn btn-outline-secondary" href="<?=e(url('seeds'))?>">Clear All</a>
        </div>
      </div>

      <details class="mt-3" <?=count($active)>5?'open':''?>>
        <summary class="fw-semibold text-success">More Filters</summary>

        <div class="row g-2 mt-1">
          <div class="col-6 col-lg-3">
            <label class="form-label">Plant Family</label>
            <select class="form-select" name="plant_family_id">
              <option value="">All</option>
              <?php foreach($ref['families'] as $r):?>
                <option value="<?=e($r['id'])?>" <?=($filters['plant_family_id']??'')==(string)$r['id']?'selected':''?>><?=e($r['name'])?></option>
              <?php endforeach?>
            </select>
          </div>

          <?php foreach([
            'plant_type'=>'Plant Type',
            'seed_source'=>'Seed Source / Brand',
            'packet_year'=>'Packet Year',
            'storage_box'=>'Seed Location',
            'companion'=>'Compatible Companion'
          ] as $key=>$label):?>
            <div class="col-6 col-lg-3">
              <label class="form-label"><?=e($label)?></label>
              <input class="form-control" name="<?=e($key)?>" value="<?=e($filters[$key]??'')?>">
            </div>
          <?php endforeach?>

          <div class="col-12 col-lg-4">
            <label class="form-label">Uses</label>
            <select class="form-select" name="uses[]" multiple size="3">
              <?php foreach($ref['uses'] as $use):?>
                <option value="<?=e($use['id'])?>" <?=in_array((string)$use['id'],array_map('strval',(array)($filters['uses']??[])),true)?'selected':''?>><?=e($use['name'])?></option>
              <?php endforeach?>
            </select>
          </div>

          <?php foreach([
            'planting_start'=>'Start Planting Date',
            'planting_end'=>'Last Planting Date'
          ] as $key=>$label):?>
            <div class="col-12 col-lg-4">
              <label class="form-label"><?=e($label)?> range (MM-DD)</label>
              <div class="input-group">
                <input class="form-control" name="<?=$key?>_from" placeholder="From MM-DD" pattern="\d{2}-\d{2}" value="<?=e($filters[$key.'_from']??'')?>">
                <input class="form-control" name="<?=$key?>_to" placeholder="To MM-DD" pattern="\d{2}-\d{2}" value="<?=e($filters[$key.'_to']??'')?>">
              </div>
            </div>
          <?php endforeach?>

          <?php foreach(array_merge($flags,[
            'indoor_start'=>'Indoor Start',
            'direct_sow'=>'Direct Sow',
            'transplant'=>'Transplant'
          ]) as $key=>$label):?>
            <div class="col-6 col-lg-2">
              <label class="form-label"><?=e($label)?></label>
              <select class="form-select" name="<?=e($key)?>">
                <option value="">Either</option>
                <option value="1" <?=($filters[$key]??'')==='1'?'selected':''?>>Yes</option>
                <option value="0" <?=($filters[$key]??'')==='0'?'selected':''?>>No</option>
              </select>
            </div>
          <?php endforeach?>

          <div class="col-6 col-lg-2">
            <label class="form-label">Sort</label>
            <select class="form-select" name="sort">
              <?php foreach([
                'seed_number'=>'Seed #',
                'name'=>'Name',
                'variety'=>'Variety',
                'category_name'=>'Category',
                'family_name'=>'Family',
                'plant_type'=>'Plant Type',
                'planting_method'=>'Method',
                'germination'=>'Germination',
                'maturity'=>'Harvest/Maturity',
                'seed_source'=>'Seed Source/Brand',
                'planting_start'=>'Start Date',
                'planting_end'=>'Last Date',
                'packet_year'=>'Packet Year',
                'storage_box'=>'Location',
                'status_name'=>'Status'
              ] as $key=>$label):?>
                <option value="<?=e($key)?>" <?=($filters['sort']??$defaultSort)===$key?'selected':''?>><?=e($label)?></option>
              <?php endforeach?>
            </select>
          </div>

          <div class="col-6 col-lg-2">
            <label class="form-label">Direction</label>
            <select class="form-select" name="direction">
              <option value="ASC" <?=strtoupper($filters['direction']??'ASC')==='ASC'?'selected':''?>>Ascending</option>
              <option value="DESC" <?=strtoupper($filters['direction']??'ASC')==='DESC'?'selected':''?>>Descending</option>
            </select>
          </div>

          <div class="col-6 col-lg-2">
            <label class="form-label">Rows Per Page</label>
            <select class="form-select" name="per_page">
              <?php foreach(inventory_page_sizes() as $n):?>
                <option value="<?=$n?>" <?=($result['per_page']===$n)?'selected':''?>><?=$n?></option>
              <?php endforeach?>
            </select>
          </div>

          <div class="col-12 d-flex flex-wrap gap-2 mt-3">
            <button class="btn btn-success" type="submit">Apply All Filters</button>
            <a class="btn btn-outline-secondary" href="<?=e(url('seeds'))?>">Clear All</a>
          </div>
        </div>
      </details>
    </form>
  </div>
</div>
<div class="card desktop-table"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><?php foreach(['seed_number'=>'Seed #','name'=>'Seed / Variety','category_name'=>'Category / Family','plant_type'=>'Type / Method','planting_start'=>'Planting','storage_box'=>'Location','status_name'=>'Status'] as $key=>$label):?><th><a href="<?=e($sortUrl($key))?>"><?=e($label)?></a></th><?php endforeach?><th>Growing Details & Flags</th><th>Actions</th></tr></thead><tbody>
<?php foreach($seeds as $s): $activeFlags=array_values(array_filter($flags,fn($label,$key)=>!empty($s[$key]),ARRAY_FILTER_USE_BOTH));?><tr><td class="fw-bold"><?=e($s['seed_number'])?></td><td><a href="<?=e(url('seeds/'.$s['id']))?>"><?=e($s['name'])?></a><div class="small text-muted"><?=e($s['variety']?:'No variety')?></div></td><td><?=e($s['category_name']?:'—')?><div class="small text-muted"><?=e($s['family_name']?:'—')?></div></td><td><?=e($s['plant_type']?:'—')?><div class="small text-muted"><?=e($s['planting_method']?:'—')?></div></td><td><strong><?=e($window($s))?></strong><?php if(display_plantable_months()):?><div class="small" title="<?=e($months($s['plantable_months']))?>"><?=e($months($s['plantable_months']))?></div><?php endif?></td><td><?=e($location($s)?:'—')?></td><td><?=e($s['status_name']?:'—')?></td><td class="small"><div><strong>Germination:</strong> <?=e($germination($s))?> · <strong>Maturity:</strong> <?=e($maturity($s))?></div><div><strong>Sun:</strong> <?=e($s['sun_requirements']?:'Not recorded')?> · <strong>Spacing:</strong> <?=e($s['spacing']?:'Not recorded')?></div><div><strong>Life cycle:</strong> <?=e($s['perennial_status']?:'Not recorded')?> · <strong>Source:</strong> <?=e($s['seed_source']?:'Not recorded')?></div><div class="text-muted"><?=e(implode(' · ',$activeFlags)?:'No key flags')?></div></td><td><div class="d-flex flex-wrap gap-1"><a class="btn btn-sm btn-outline-secondary" href="<?=e(url('seeds/'.$s['id']))?>">View</a><?php if(!demo_read_only()):?><a class="btn btn-sm btn-outline-success" href="<?=e(url('seeds/'.$s['id'].'/edit'))?>">Edit</a><form method="post" action="<?=e(url('seeds/'.$s['id'].'/duplicate'))?>"><?=csrf_field()?><button class="btn btn-sm btn-outline-secondary">Duplicate</button></form><form method="post" action="<?=e(url('seeds/'.$s['id'].'/delete'))?>" data-confirm="Delete this seed?"><?=csrf_field()?><button class="btn btn-sm btn-outline-danger">Delete</button></form><?php endif?></div></td></tr><?php endforeach?></tbody></table></div></div>
<div class="mobile-card"><?php foreach($seeds as $s):?><article class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between"><h2 class="h5"><a href="<?=e(url('seeds/'.$s['id']))?>"><?=e($s['name'])?></a></h2><strong>#<?=e($s['seed_number'])?></strong></div><p><?=e($s['variety']?:'No variety')?> · <?=e($s['category_name']?:'Uncategorized')?> · <?=e($s['family_name']?:'No family')?></p><dl class="row small"><dt class="col-5">Type / Method</dt><dd class="col-7"><?=e($s['plant_type']?:'—')?> / <?=e($s['planting_method']?:'—')?></dd><dt class="col-5">Planting Window</dt><dd class="col-7"><?=e($window($s))?></dd><?php if(display_plantable_months()):?><dt class="col-5">Plantable Months</dt><dd class="col-7"><?=e($months($s['plantable_months']))?></dd><?php endif?><dt class="col-5">Germination</dt><dd class="col-7"><?=e($germination($s))?></dd><dt class="col-5">Harvest/Maturity</dt><dd class="col-7"><?=e($maturity($s))?></dd><dt class="col-5">Sun / Spacing</dt><dd class="col-7"><?=e($s['sun_requirements']?:'Not recorded')?> / <?=e($s['spacing']?:'Not recorded')?></dd><dt class="col-5">Life cycle</dt><dd class="col-7"><?=e($s['perennial_status']?:'Not recorded')?></dd><dt class="col-5">Seed Source/Brand</dt><dd class="col-7"><?=e($s['seed_source']?:'Not recorded')?></dd><dt class="col-5">Important Flags</dt><dd class="col-7"><?php $mobileFlags=array_values(array_filter($flags,fn($label,$key)=>!empty($s[$key]),ARRAY_FILTER_USE_BOTH)); ?><?=e(implode(' · ',$mobileFlags)?:'None recorded')?></dd><dt class="col-5">Location / Status</dt><dd class="col-7"><?=e($location($s)?:'—')?> / <?=e($s['status_name']?:'—')?></dd></dl><div class="d-grid gap-2"><a class="btn btn-outline-secondary" href="<?=e(url('seeds/'.$s['id']))?>">View</a><?php if(!demo_read_only()):?><a class="btn btn-outline-success" href="<?=e(url('seeds/'.$s['id'].'/edit'))?>">Edit</a><form method="post" action="<?=e(url('seeds/'.$s['id'].'/duplicate'))?>"><?=csrf_field()?><button class="btn btn-outline-secondary w-100">Duplicate</button></form><form method="post" action="<?=e(url('seeds/'.$s['id'].'/delete'))?>" data-confirm="Delete this seed?"><?=csrf_field()?><button class="btn btn-outline-danger w-100">Delete</button></form><?php endif?></div></div></article><?php endforeach?></div>
<?php if(!$filterErrors&&!$seeds&&$result['overall_total']===0):?><div class="alert alert-info">Your seed library has no records yet.<?php if(!demo_read_only()):?> <a href="<?=e(url('seeds/create'))?>">Add your first seed</a>.<?php endif?></div><?php elseif(!$filterErrors&&!$seeds):?><div class="alert alert-info">Seeds are saved in your library, but none match the current search and filters. <a href="<?=e(url('seeds'))?>">Clear all filters</a>.</div><?php endif?>
<?php if($result['pages']>1):?><nav class="mt-3" aria-label="Inventory pages"><ul class="pagination flex-wrap"><li class="page-item <?=$result['page']<=1?'disabled':''?>"><a class="page-link" href="<?=e($link(['page'=>max(1,$result['page']-1)]))?>">Previous</a></li><?php for($p=max(1,$result['page']-2);$p<=min($result['pages'],$result['page']+2);$p++):?><li class="page-item <?=$p===$result['page']?'active':''?>"><a class="page-link" href="<?=e($link(['page'=>$p]))?>"><?=$p?></a></li><?php endfor?><li class="page-item <?=$result['page']>=$result['pages']?'disabled':''?>"><a class="page-link" href="<?=e($link(['page'=>min($result['pages'],$result['page']+1)]))?>">Next</a></li></ul></nav><?php endif?>
