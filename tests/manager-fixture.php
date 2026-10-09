<?php
require __DIR__.'/bootstrap.php';
use Illuminate\Support\Facades\DB;
use EvolutionCMS\Models\SiteContent;
$path=dirname(__DIR__).'/.local/manager-fixture.json';
$command=$argv[1]??'state';
if($command==='init') {
    if(is_file($path)) throw new RuntimeException('Clean up existing HTTP fixtures first');
    $password=json_decode(stream_get_contents(STDIN),true)['password'];
    $f=['udperms'=>DB::table('system_settings')->where('setting_name','use_udperms')->value('setting_value')];
    $f['seostrict']=DB::table('system_settings')->where('setting_name','seostrict')->value('setting_value');
    $f['role']=DB::table('user_roles')->insertGetId(['name'=>'PR543 HTTP role']);
    foreach(['frames','home','view_document','new_document','edit_document','save_document','action_ok','logout','view_unpublished','access_permissions','role_actionok'] as $p) DB::table('role_permissions')->insert(['role_id'=>$f['role'],'permission'=>$p]);
    $u=EvolutionCMS\Models\User::create(['username'=>'pr543_http_editor','password'=>$modx->getPasswordHash()->HashPassword($password)]);
    $u->attributes()->create(['fullname'=>'PR543 HTTP Editor','email'=>'pr543@example.test','role'=>$f['role'],'blocked'=>0,'verified'=>1]);$f['user']=$u->id;
    DB::table('user_attributes')->where('internalKey',$u->id)->update(['role'=>$f['role']]);
    $f['group']=DB::table('documentgroup_names')->insertGetId(['name'=>'PR543 HTTP private']);
    $f['membergroup']=DB::table('membergroup_names')->insertGetId(['name'=>'PR543 HTTP membergroup']);
    DB::table('membergroup_access')->insert(['membergroup'=>$f['membergroup'],'documentgroup'=>$f['group'],'context'=>0]);
    $f['template']=DB::table('site_templates')->insertGetId(['templatename'=>'PR543 HTTP','content'=>'[*content*]']);
    $f['tv']=DB::table('site_tmplvars')->insertGetId(['name'=>'pr543_http_tv','type'=>'text','caption'=>'Private TV','default_text'=>'default']);
    DB::table('site_tmplvar_templates')->insert(['tmplvarid'=>$f['tv'],'templateid'=>$f['template']]);
    DB::table('site_tmplvar_access')->insert(['tmplvarid'=>$f['tv'],'documentgroup'=>$f['group']]);
    $parent=SiteContent::create(['pagetitle'=>'PR543 HTTP parent','alias'=>'pr543-http-parent','parent'=>0,'template'=>0,'privatemgr'=>1,'published'=>0]);$f['parent']=$parent->id;
    DB::table('document_groups')->insert(['document'=>$parent->id,'document_group'=>$f['group']]);
    DB::table('system_settings')->where('setting_name','use_udperms')->update(['setting_value'=>'1']);
    DB::table('system_settings')->where('setting_name','seostrict')->update(['setting_value'=>'0']);
    file_put_contents($path,json_encode($f));$modx->clearCache('full');
    echo 'FIXTURE_JSON '.json_encode($f)."\n";
} elseif($command==='cleanup') {
    if(!is_file($path)) exit;
    $f=json_decode(file_get_contents($path),true);
    $ids=SiteContent::withTrashed()->where('pagetitle','like','PR543 HTTP%')->pluck('id');
    DB::table('document_groups')->whereIn('document',$ids)->delete();DB::table('site_tmplvar_contentvalues')->whereIn('contentid',$ids)->delete();
    DB::table('site_content')->whereIn('id',$ids)->delete();
    EvolutionCMS\Models\User::find($f['user'])?->delete();
    DB::table('role_permissions')->where('role_id',$f['role'])->delete();DB::table('user_roles')->where('id',$f['role'])->delete();
    DB::table('membergroup_access')->where('membergroup',$f['membergroup'])->delete();DB::table('membergroup_names')->where('id',$f['membergroup'])->delete();DB::table('documentgroup_names')->where('id',$f['group'])->delete();
    DB::table('site_tmplvar_templates')->where('tmplvarid',$f['tv'])->delete();DB::table('site_tmplvar_access')->where('tmplvarid',$f['tv'])->delete();DB::table('site_tmplvars')->where('id',$f['tv'])->delete();DB::table('site_templates')->where('id',$f['template'])->delete();
    DB::table('system_settings')->where('setting_name','use_udperms')->update(['setting_value'=>$f['udperms']]);
    DB::table('system_settings')->where('setting_name','seostrict')->update(['setting_value'=>$f['seostrict']]);
    unlink($path);$modx->clearCache('full');echo "FIXTURE_JSON {}\n";
} else {
    $rows=SiteContent::withTrashed()->where('pagetitle','like','PR543 HTTP%')->get()->toArray();
    foreach($rows as &$row) $row['tvs']=DB::table('site_tmplvar_contentvalues')->where('contentid',$row['id'])->pluck('value','tmplvarid')->toArray();
    echo 'FIXTURE_JSON '.json_encode($rows)."\n";
}
