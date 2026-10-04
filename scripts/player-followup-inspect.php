<?php
use RadioRubben\Fotballrobot\Facts;
use RadioRubben\Fotballrobot\PlayerFacts;
try {
 $dir=dirname(__FILE__).'/evidence';if(!is_dir($dir))mkdir($dir,0700);
 $out=['checked_at'=>gmdate(DATE_ATOM),'files'=>[]];
 foreach(['player-facts.php','players.php','radio-rubben-fotballrobot.php'] as $file){$path=WP_PLUGIN_DIR.'/radio-rubben-fotballrobot/'.($file==='radio-rubben-fotballrobot.php'?'':'includes/').$file;$out['files'][$file]=hash_file('sha256',$path);}
 foreach(['torbjorn'=>'/fotballdata/person/profil/?fiksId=2039307','tiril'=>'/fotballdata/person/profil/?fiksId=3942773','match'=>'/fotballdata/kamp/?fiksId=8989882','hodd'=>'/fotballdata/lag/hjem/?fiksId=171'] as $name=>$path){
  $r=wp_safe_remote_get('https://www.fotball.no'.$path,['timeout'=>15,'redirection'=>0,'limit_response_size'=>2500000]);
  if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)throw new RuntimeException('Source unavailable: '.$name);
  $html=wp_remote_retrieve_body($r);file_put_contents($dir.'/'.$name.'.html',$html);
  if($name==='match'){$out['tiril_participation']=PlayerFacts::participation($html,8989882,3942773);$x=Facts::dom($html);$out['match_text']=Facts::text($x->query('//main')->item(0));}
  if(in_array($name,['torbjorn','tiril'],true)){$x=Facts::dom($html);$table=$x->query('//table[thead/tr/th[@title="Sesong"]]')->item(0);$rows=[];foreach($x->query('.//tr[td]',$table) as $tr){$cells=$x->query('./td',$tr);$a=$x->query('.//a[@data-stat-type]',$tr);$links=[];foreach($a as $node){$v=[];foreach($node->attributes as $attr)if(str_starts_with($attr->name,'data-'))$v[$attr->name]=$attr->value;$links[]=$v;}$rows[]=['text'=>Facts::text($tr),'cells'=>$cells->length,'links'=>$links];}$out[$name.'_stats']=$rows;}
 }
 file_put_contents($dir.'/summary.json',wp_json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
 echo "FOLLOWUP_READ_OK\n";
} catch(Throwable $e){WP_CLI::error($e->getMessage());}
