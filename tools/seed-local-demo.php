<?php
require dirname(__DIR__) . '/tests/bootstrap.php';
use EvolutionCMS\Models\SiteSnippet;
use EvolutionCMS\Models\SiteContent;
use EvolutionCMS\Models\SitePlugin;
use Illuminate\Support\Facades\DB;
foreach (['DocLister','FormLister'] as $name) {
 SiteSnippet::updateOrCreate(['name'=>$name], ['snippet'=>'return require MODX_BASE_PATH . "assets/snippets/'.$name.'/snippet.'.$name.'.php";', 'properties'=>'', 'disabled'=>0]);
}
SiteSnippet::updateOrCreate(['name'=>'CompatibilityForm'], ['snippet'=> <<<'CODE'
return $modx->runSnippet('FormLister', [
 'formid'=>'compatibility', 'noemail'=>1,
 'rules'=>['name'=>['required'=>['message'=>'Enter your name']]],
 'formTpl'=>'@CODE:<form method="post"><input type="hidden" name="formid" value="compatibility"><label>Name <input name="name" value="[+name.value+]"></label><button type="submit">Test form</button><p>[+name.error+]</p></form>',
 'successTpl'=>'@CODE:<p id="form-success">FormLister: submitted successfully</p>',
 'protectSubmit'=>0
]);
CODE
, 'properties'=>'','disabled'=>0]);
SiteSnippet::updateOrCreate(['name'=>'LegacyCompatibility'], ['snippet'=> <<<'CODE'
$modx->setPlaceholder('legacy_placeholder', 'Placeholders work');
$count = $modx->db->getValue($modx->db->select('COUNT(*)', $modx->getFullTableName('site_content'), 'deleted=0'));
return '<p id="legacy-result">Legacy $modx API: ' . (int)$count . ' resources; snippet parameters: ' . htmlspecialchars($label ?? '') . '</p>';
CODE
, 'properties'=>'','disabled'=>0]);
$plugin=SitePlugin::updateOrCreate(['name'=>'LegacyCompatibilityPlugin'], ['plugincode'=> <<<'CODE'
if ($modx->event->name === 'OnWebPagePrerender') {
 $modx->documentOutput = str_replace('<!-- compatibility-plugin -->', '<p id="plugin-result">Legacy plugin OnWebPagePrerender: OK</p>', $modx->documentOutput);
}
CODE
, 'properties'=>'','disabled'=>0]);
$event=DB::table('system_eventnames')->where('name','OnWebPagePrerender')->value('id');
DB::table('site_plugin_events')->updateOrInsert(['pluginid'=>$plugin->id,'evtid'=>$event],['priority'=>0]);
$page=SiteContent::findOrFail(1);
$page->pagetitle='Evolution CMS on Laravel 13';
$page->template=0;$page->cacheable=0;
$page->content= <<<'HTML'
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>Evolution CMS · Laravel 13 compatibility lab</title><style>body{font:18px/1.6 system-ui;background:#f4f6fa;color:#192538;max-width:950px;margin:50px auto;padding:0 25px}h1{font-size:38px;line-height:1.2}section{background:white;padding:20px 30px;margin:20px 0;border-radius:12px;border:1px solid #dce3ee}small{color:#53637a}input,button{font:inherit;padding:8px;border:1px solid #becbdd;border-radius:6px}button{background:#234fde;color:white;cursor:pointer}a{color:#234fde}</style></head><body><small>LOCAL TEST SITE · PHP 8.4 · ILLUMINATE 13.35</small><h1>Evolution CMS compatibility lab</h1><p>The upgraded CMS renders this page from the existing database.</p><section><h2>Legacy extensions</h2>[!LegacyCompatibility? &label=`passed`!]<p>[+legacy_placeholder+]</p><!-- compatibility-plugin --></section><section><h2>DocLister</h2>[[DocLister? &parents=`0` &depth=`0` &tpl=`@CODE:<li>[+pagetitle+]</li>` &ownerTPL=`@CODE:<ul>[+dl.wrap+]</ul>`]]</section><section><h2>FormLister</h2><p>Local validation and submission test. Email is disabled.</p>[!CompatibilityForm!]</section><p><a href="/manager/">Open CMS manager</a></p></body></html>
HTML;
$page->save();$modx->clearCache('full');
echo "DEMO_SEEDED\n";
