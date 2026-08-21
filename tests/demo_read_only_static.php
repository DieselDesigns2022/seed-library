<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
$index=file_get_contents($root.'/public/index.php');
$view=file_get_contents($root.'/app/view.php');
$inventory=file_get_contents($root.'/app/templates/seeds_index.php');
$detail=file_get_contents($root.'/app/templates/seed_detail.php');
$garden=file_get_contents($root.'/app/templates/garden_index.php');
$winter=file_get_contents($root.'/app/templates/winter_sowing.php');
$exampleConfig=require $root.'/config.example.php';
$allowedGetRoutes=['dashboard','seeds','seeds/123','calendar','garden','winter-sowing','companions'];
$forbiddenGetRoutes=['login','seeds/create','seeds/123/edit','garden/create','winter-sowing/123/research','import','export','print','backup','settings','manage/categories','manage/families','manage/uses','manage/statuses','manage/storage'];
$allMatch=fn(array $values,callable $test): bool=>count(array_filter($values,$test))===count($values);
$checks=[
 'example config defaults demo mode off'=>($exampleConfig['app']['demo_read_only']??null)===false,
 'normal mode still requires authentication'=>str_contains($index,"if (!demo_read_only()) require_auth();"),
 'approved GET routes are allowed by policy'=>$allMatch($allowedGetRoutes,fn(string $route): bool=>demo_access_policy('GET',$route)===null),
 'write and admin GET routes are forbidden by policy'=>$allMatch($forbiddenGetRoutes,fn(string $route): bool=>demo_access_policy('GET',$route)===403),
 'POST to approved route is method not allowed by policy'=>demo_access_policy('POST','dashboard')===405,
 'request policy runs before handlers'=>strpos($index,'enforce_demo_access($path);')<strpos($index,"if (\$path === 'login')"),
 'enforcer preserves method response'=>str_contains(file_get_contents($root.'/app/bootstrap.php'),"header('Allow: GET')")&&str_contains(file_get_contents($root.'/app/bootstrap.php'),"exit('Portfolio Demo — Read Only')"),
 'demo notice and browse navigation render'=>str_contains($view,'Portfolio Demo — Read Only')&&str_contains($view,'current_user() || demo_read_only()'),
 'operational navigation is demo guarded'=>str_contains($view,'if(!demo_read_only())')&&str_contains($view,'Tools'),
 'inventory mutations are demo guarded'=>substr_count($inventory,'if(!demo_read_only())')>=4,
 'seed detail mutations are demo guarded'=>str_contains($detail,'if(!demo_read_only())'),
 'normal seed detail retains History section'=>str_contains($detail,'<h2 class="h5">History</h2>'),
 'demo seed detail suppresses entire History section'=>str_contains($detail,"<?php if(!demo_read_only()):?>\n<div class=\"col-12\"><section class=\"card\"><div class=\"card-body\"><h2 class=\"h5\">History</h2>")&&str_contains($detail,"</div></section></div>\n<?php endif?>\n</div>"),
 'garden controls are demo guarded'=>substr_count($garden,'if(!demo_read_only())')>=2,
 'winter create and editable research are demo guarded'=>substr_count($winter,'if(!demo_read_only())')>=1&&str_contains($winter,'if(demo_read_only())')&&str_contains($winter,'Structured Research Fields'),
];
$failed=0;foreach($checks as $name=>$ok){echo($ok?'PASS':'FAIL').": $name\n";if(!$ok)$failed++;}exit($failed?1:0);
