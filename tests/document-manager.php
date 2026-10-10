<?php
require __DIR__ . '/bootstrap.php';
use EvolutionCMS\Models\SiteContent;
use Illuminate\Support\Facades\DB;

$checks = [];
function dmCheck($name, callable $test) {
    global $checks;
    try { $test(); $checks[] = ['test'=>$name, 'status'=>'PASS']; }
    catch (Throwable $e) { $checks[] = ['test'=>$name, 'status'=>'FAIL', 'error'=>$e->getMessage()]; }
}
function dmAssert($condition, $message = 'Assertion failed') { if (!$condition) throw new RuntimeException($message); }
function dmData($extra = []) { return array_merge(['pagetitle'=>'PR543 regression', 'type'=>'document', 'parent'=>0, 'template'=>0, 'published'=>1, 'pub_date'=>'', 'unpub_date'=>'', 'alias'=>'', 'content'=>'test'], $extra); }

$_SESSION['mgrInternalKey'] = 1;
$_SESSION['mgrRole'] = 1;
$events = [];
$names = ['OnBeforeDocSave','OnDocSave','OnBeforeDocFormSave','OnDocFormSave', 'OnBeforeDocDelete','OnDocDelete','OnBeforeDocFormDelete','OnDocFormDelete', 'OnDocUndelete','OnDocFormUnDelete','OnDocPublish','OnDocPublished','OnDocUnpublish','OnDocUnPublished', 'OnBeforeDocSetGroups','OnDocSetGroups','OnBeforeDocDuplicate','OnDocDuplicate'];
foreach ($names as $name) $modx['events']->listen('evolution.'.$name, function($params) use (&$events, $name) { $events[] = ['name'=>$name, 'params'=>$params]; });
DB::beginTransaction();
try {
    dmCheck('Update seeder preserves legacy event IDs and is idempotent', function() {
        $old = DB::table('system_eventnames')->where('name','OnDocFormSave')->value('id');
        require_once dirname(__DIR__).'/install/stubs/seeds/update/SystemEventnamesTableSeeder.php';
        $seed = new EvolutionCMS\Installer\Update\SystemEventnamesTableSeeder();
        $seed->run(); $seed->run();
        foreach (EvolutionCMS\Support\DocumentEventCompatibility::ALIASES as $new=>$legacy) {
            dmAssert(DB::table('system_eventnames')->where('name',$new)->count()===1);
            dmAssert(DB::table('system_eventnames')->where('name',$legacy)->count()===1);
        }
        if ($old !== null) dmAssert(DB::table('system_eventnames')->where('name','OnDocFormSave')->value('id')===$old);
    });
    dmCheck('Stored PHP plugin receives old event name, mode and mutable doc', function() use($modx) {
        EvolutionCMS\Models\SitePlugin::create(['name'=>'Pr543LegacyProbe','plugincode'=>'if ($modx->event->name === "OnBeforeDocFormSave" && $mode === "new") { $modx->event->params["doc"]["description"] = "stored legacy plugin"; }','properties'=>'','disabled'=>0]);
        $modx->pluginEvent['OnBeforeDocFormSave']=['Pr543LegacyProbe'];
        try { $probe=DocumentManager::create(dmData(),true,false);dmAssert($probe->description==='stored legacy plugin'); }
        finally { unset($modx->pluginEvent['OnBeforeDocFormSave']); }
    });
    $events=[];
    $page = DocumentManager::create(dmData(), true, false);
    dmCheck('Create preserves published checkbox with empty scheduling fields', fn()=>dmAssert((int)$page->published === 1));
    dmCheck('Create calls new and legacy save events once', function() use(&$events, $page) {
        foreach (['OnBeforeDocSave','OnDocSave','OnBeforeDocFormSave','OnDocFormSave'] as $name) dmAssert(count(array_filter($events, fn($e)=>$e['name']===$name))===1, $name);
        $old = array_values(array_filter($events, fn($e)=>$e['name']==='OnDocFormSave'))[0]['params'];
        dmAssert($old['mode']==='new' && (int)$old['id']===$page->id && $old['doc']['pagetitle']===$page->pagetitle);
    });
    $modx['events']->listen('evolution.OnBeforeDocFormSave', function($params) { if (($params['mode'] ?? '') === 'upd') $params['doc']['description'] = 'legacy mutation'; });
    DocumentManager::edit(dmData(['id'=>$page->id, 'pagetitle'=>'Updated']), true, false);
    dmCheck('Legacy before-save listener can mutate document by reference', fn()=>dmAssert($page->fresh()->description==='legacy mutation'));
    $template = DB::table('site_templates')->insertGetId(['templatename'=>'PR543 test', 'content'=>'[*content*]']);
    $tv = DB::table('site_tmplvars')->insertGetId(['name'=>'pr543_tv', 'type'=>'text', 'caption'=>'PR543', 'default_text'=>'default']);
    DB::table('site_tmplvar_templates')->insert(['tmplvarid'=>$tv,'templateid'=>$template]);
    DocumentManager::edit(dmData(['id'=>$page->id,'template'=>$template,'pr543_tv'=>'value']),false,false);
    dmCheck('TV value persists', fn()=>dmAssert(DB::table('site_tmplvar_contentvalues')->where('contentid',$page->id)->where('tmplvarid',$tv)->value('value')==='value'));
    DocumentManager::edit(dmData(['id'=>$page->id,'template'=>$template,'pr543_tv'=>null]),false,false);
    dmCheck('Explicit null clears TV override', fn()=>dmAssert(!DB::table('site_tmplvar_contentvalues')->where('contentid',$page->id)->where('tmplvarid',$tv)->exists()));
    DocumentManager::edit(dmData(['id'=>$page->id,'template'=>$template,'pr543_tv'=>'value']),false,false);
    DocumentManager::edit(dmData(['id'=>$page->id,'template'=>$template]),false,false);
    dmCheck('Omitted TV preserves existing override', fn()=>dmAssert(DB::table('site_tmplvar_contentvalues')->where('contentid',$page->id)->where('tmplvarid',$tv)->value('value')==='value'));
    DocumentManager::edit(dmData(['id'=>$page->id,'template'=>$template,'pr543_tv'=>'default']),false,false);
    dmCheck('Default TV clears override', fn()=>dmAssert(!DB::table('site_tmplvar_contentvalues')->where('contentid',$page->id)->where('tmplvarid',$tv)->exists()));
    $group = DB::table('documentgroup_names')->insertGetId(['name'=>'PR543 test']);
    DB::table('membergroup_access')->insert(['membergroup'=>1, 'documentgroup'=>$group, 'context'=>0]);
    DB::table('membergroup_access')->insert(['membergroup'=>1, 'documentgroup'=>$group, 'context'=>1]);
    DocumentManager::setGroups(['id'=>$page->id,'document_groups'=>[$group]], true, false);
    dmCheck('SetGroups updates group links and both privacy flags', function() use($page,$group) { $p=$page->fresh();dmAssert(DB::table('document_groups')->where('document',$p->id)->where('document_group',$group)->exists() && (int)$p->privatemgr===1 && (int)$p->privateweb===1); });
    dmCheck('Duplicate retains inherited group privacy flags', function()use($page) {
        $copy=DocumentManager::duplicate(['id'=>$page->id],false,false);
        dmAssert((int)$copy->fresh()->privatemgr===1 && (int)$copy->fresh()->privateweb===1);
    });
    DocumentManager::setGroups(['id'=>$page->id,'document_groups'=>[]], true, false);
    dmCheck('Clear groups resets privacy flags', function()use($page) { $p=$page->fresh();dmAssert(!DB::table('document_groups')->where('document',$p->id)->exists() && (int)$p->privatemgr===0 && (int)$p->privateweb===0); });
    $events=[];
    DocumentManager::unpublish(['id'=>$page->id],true,false);
    DocumentManager::publish(['id'=>$page->id],true,false);
    dmCheck('Publish/unpublish dispatch both event APIs with legacy docid', function()use(&$events,$page) { foreach(['OnDocPublish','OnDocUnpublish','OnDocPublished','OnDocUnPublished'] as $name) { $found=array_values(array_filter($events,fn($e)=>$e['name']===$name));dmAssert(count($found)===1,$name);if(in_array($name,['OnDocPublished','OnDocUnPublished']))dmAssert((int)$found[0]['params']['docid']===$page->id); }dmAssert((int)$page->fresh()->published===1); });
    $child=DocumentManager::create(dmData(['parent'=>$page->id]),false,false);
    $events=[];
    DocumentManager::delete(['id'=>$page->id],true,false);
    dmCheck('Delete includes descendants and both legacy events', function()use(&$events,$page,$child) {dmAssert(SiteContent::find($page->id)===null && SiteContent::find($child->id)===null);foreach(['OnBeforeDocFormDelete','OnDocFormDelete'] as $name){$found=array_values(array_filter($events,fn($e)=>$e['name']===$name));dmAssert(count($found)===1 && in_array($child->id,$found[0]['params']['children']),$name);} });
    DocumentManager::undelete(['id'=>$page->id],true,false);
    dmCheck('Undelete restores descendants and legacy event', function()use(&$events,$page,$child){dmAssert(SiteContent::find($page->id)!==null && SiteContent::find($child->id)!==null);dmAssert(count(array_filter($events,fn($e)=>$e['name']==='OnDocFormUnDelete'))===1);});
    $events=[];
    $copy=DocumentManager::duplicate(['id'=>$page->id],false,false);
    dmCheck('Duplicate preserves tree and events=false suppresses nested events', function()use(&$events,$copy){dmAssert(SiteContent::where('parent',$copy->id)->count()===1);dmAssert($events===[], 'Nested events unexpectedly emitted');});
    dmCheck('Moving into a deleted parent preserves deleted status', function()use($page) {
        $parent=DocumentManager::create(dmData(),false,false);
        DocumentManager::delete(['id'=>$parent->id],false,false);
        DocumentManager::edit(dmData(['id'=>$page->id,'parent'=>$parent->id]),false,false);
        dmAssert((int)SiteContent::withTrashed()->find($page->id)->deleted===1);
        DocumentManager::edit(dmData(['id'=>$page->id,'parent'=>0,'deleted'=>0]),false,false);
    });
    dmCheck('Site start cannot be deleted', function() {try{DocumentManager::delete(['id'=>1],false,false);throw new RuntimeException('Protected resource deleted');}catch(EvolutionCMS\Exceptions\ServiceActionException $e){dmAssert(SiteContent::find(1)!==null);} });
    dmCheck('Resource cannot become its own parent', function()use($page){try{DocumentManager::edit(dmData(['id'=>$page->id,'parent'=>$page->id]),false,false);throw new RuntimeException('Self-parent accepted');}catch(EvolutionCMS\Exceptions\ServiceActionException $e){dmAssert((int)$page->fresh()->parent===0);} });
} finally { DB::rollBack(); }
file_put_contents(dirname(__DIR__).'/reports/document-manager.json',json_encode($checks,JSON_PRETTY_PRINT));
foreach($checks as $check) echo $check['status'].' '.$check['test'].(isset($check['error'])?' — '.$check['error']:'')."\n";
$failed=count(array_filter($checks,fn($c)=>$c['status']==='FAIL'));
echo 'DOCUMENT_MANAGER_COMPLETE '.count($checks).' checks, '.$failed." failed\n";
exit($failed?1:0);
