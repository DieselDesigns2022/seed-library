<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 garden-page-heading">
    <div><p class="eyebrow mb-1">Planting journal</p><h1 class="mb-1">My Garden</h1><p class="text-muted mb-0">Independent records of what you actually planted. Actual dates always take precedence over estimates.</p></div>
    <?php if(!demo_read_only()):?><a class="btn btn-success" href="<?=e(url('garden/create'))?>">Add Planting</a><?php endif?>
</div>
<?php foreach($groups as $heading=>$plantings):$sectionId='garden-'.strtolower(str_replace(' ','-',$heading));$needsAttention=$heading==='Needs Attention';?>
<section class="garden-section <?=$needsAttention?'garden-section-attention':''?>" aria-labelledby="<?=e($sectionId)?>">
    <div class="garden-section-heading">
        <div><h2 id="<?=e($sectionId)?>" class="h4 mb-0"><?=e($heading)?> <span class="garden-count" aria-label="<?=count($plantings)?> plantings"><?=count($plantings)?></span></h2><?php if($needsAttention):?><p class="mb-0">Expected milestones have passed and may need an update.</p><?php endif?></div>
    </div>
    <?php if(!$plantings):?><div class="garden-empty">No plantings in this section.</div><?php else:?><div class="row g-3"><?php foreach($plantings as $p):?><div class="col-12 col-xl-6"><article class="card garden-planting-card h-100 <?=$needsAttention?'needs-attention':''?>"><div class="card-body">
        <header class="garden-card-header"><h3 class="h5 mb-0"><?php if(!demo_read_only()):?><a href="<?=e(url('garden/'.$p['id']))?>"><?php endif?><?=e($p['seed_name'])?><?=($p['variety']??'')!==''?' <span class="garden-variety">— '.e($p['variety']).'</span>':''?><?php if(!demo_read_only()):?></a><?php endif?></h3><span class="garden-status status-<?=e(strtolower($p['status']))?>"><?=e($p['status'])?></span></header>
        <dl class="garden-primary-details">
            <div><dt>Actual planted</dt><dd><?=e(garden_display_date($p['planted_date']))?> <span class="detail-separator">·</span> <?=e($p['planting_method'])?> <span class="detail-separator">·</span> Qty <?=e($p['quantity'])?></dd></div>
            <div><dt>Location</dt><dd><?=e($p['location'])?></dd></div>
        </dl>
        <div class="garden-date-grid">
        <?php foreach(['_germination'=>'Expected germination','_transplant'=>'Expected transplant','_harvest'=>'Expected harvest'] as $key=>$label):?><div class="garden-date-row"><span class="garden-label"><?=e($label)?></span><?php if($p[$key]):?><span class="garden-date-value"><?=e(garden_display_date($p[$key][0]))?><?= $p[$key][1]!==$p[$key][0]?' – '.e(garden_display_date($p[$key][1])):''?> <span class="calculated">Calculated</span></span><?php else:?><span class="garden-unavailable">Unavailable — not enough supported data<?=(($key==='_transplant'&&!empty($p['actual_transplant_date']))||($key==='_harvest'&&!empty($p['actual_harvest_date'])))?' or actual date recorded':''?>.</span><?php endif?></div><?php endforeach?>
        <?php if(!empty($p['actual_transplant_date'])):?><div class="garden-date-row actual-date"><span class="garden-label">Actual transplant</span><span class="garden-date-value"><?=e(garden_display_date($p['actual_transplant_date']))?></span></div><?php endif?>
        <?php if(!empty($p['actual_harvest_date'])):?><div class="garden-date-row actual-date"><span class="garden-label">Actual harvest</span><span class="garden-date-value"><?=e(garden_display_date($p['actual_harvest_date']))?></span></div><?php endif?>
        </div>
        <?php if($needsAttention&&!empty($p['_attention_reasons'])):?><div class="garden-attention-note"><strong>Why this needs attention</strong><ul class="mb-0 mt-1"><?php foreach($p['_attention_reasons'] as $reason):?><li><?=e($reason)?></li><?php endforeach?></ul></div><?php endif?>
        <?php if(!demo_read_only()):?><footer class="garden-card-actions"><a class="btn btn-sm btn-outline-success" href="<?=e(url('garden/'.$p['id']))?>">View / Edit</a><?php if(!in_array($p['status'],['Archived','Harvested','Failed'],true)):?><form method="post" action="<?=e(url('garden/'.$p['id'].'/status'))?>"><?=csrf_field()?><input type="hidden" name="status" value="Archived"><button class="btn btn-sm btn-outline-secondary">Archive</button></form><?php endif?></footer><?php endif?>
    </div></article></div><?php endforeach?></div><?php endif?>
</section>
<?php endforeach?>
