<?php
/** Live model/catalog pilot in memory, then the single private activation flag. */
declare(strict_types=1);
function rrni_pilot_assert(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function rrni_pilot_private_write(string $path,string $bytes):void{
    $tmp=tempnam(dirname($path),'.rrni-');
    try{rrni_pilot_assert($tmp!==false&&file_put_contents($tmp,$bytes)===strlen($bytes)&&chmod($tmp,0600)&&rename($tmp,$path),'Private atomic write failed');}
    finally{if($tmp&&is_file($tmp))unlink($tmp);}
}
rrni_pilot_assert(PHP_SAPI==='cli','CLI required');
$root='/customers/9/3/1/cptk37ymg/webroots/r1417157';
rrni_pilot_assert(realpath('/run/webroots/r1417157')===$root,'Unexpected site root');
$private=$root.'/studio-private';$work=getenv('HOME').'/.radiorubben-studio-deploy';
rrni_pilot_assert(realpath($private)===$private&&!is_link($private),'Unexpected private Studio directory');
$lock=fopen($work.'/deploy.lock','c');
rrni_pilot_assert($lock!==false&&flock($lock,LOCK_EX|LOCK_NB),'Another Studio deployment is running');
try{
    $state=json_decode(file_get_contents($work.'/selective-state.json'),true,512,JSON_THROW_ON_ERROR);
    rrni_pilot_assert($state['commit']==='96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60','Expected reviewed Studio release is not deployed');
    foreach($state['files']as$path=>$hashes){
        $file=$root.'/'.$path;$actual=is_file($file)?hash_file('sha256',$file):null;
        rrni_pilot_assert(!is_link($file)&&$actual===$hashes['live'],'Studio code drift: '.$path);
    }
    $protected=[];
    foreach(new DirectoryIterator($private.'/config')as$f)if($f->isFile()&&$f->getFilename()!=='news-images-enabled.php')$protected[$f->getPathname()]=hash_file('sha256',$f->getPathname());
    $flag=$private.'/config/news-images-enabled.php';
    rrni_pilot_assert(!is_link($flag),'Activation flag must not be a symlink');
    $before=is_file($flag)?file_get_contents($flag):null;
    rrni_pilot_assert($before===null||in_array($before,["<?php\nreturn true;\n","<?php\nreturn false;\n"],true),'Unknown activation file; refusing overwrite');
    $backup=$work.'/backups/news-images-activation-'.basename(__DIR__);
    rrni_pilot_assert(!file_exists($backup)&&mkdir($backup,0700),'Activation backup must be new');
    rrni_pilot_private_write($backup.'/before.json',json_encode(['flag_content'=>$before,'protected_hashes'=>$protected,'studio_commit'=>$state['commit']],JSON_THROW_ON_ERROR));
    require_once $private.'/app/config.php';
    require_once $private.'/app/producer.php';
    require_once $private.'/app/web-publish.php';
    $config=load_config();$config['news_images_enabled']=true;
    rrni_pilot_assert(!empty($config['openai_api_key'])&&!empty($config['openai_model']),'Studio model connection is not configured');
    $catalog=studio_images_catalog();
    $fixtures=[
        ['bremnes','bremnes-fotball','Bremnes IL vant på hjemmebane','Bremnes ILs fotballag vant 3–1 på Sentralidrettsanlegget.','Saken handler om Bremnes ILs lag, samspillet og treningen fram mot neste kamp. Det er klubbens prestasjon på fotballbanen som er hovedtema.','sport',['clubs'=>['Bremnes IL'],'places'=>['Bømlo']]],
        ['brann','sport','Brann trener før neste kamp','Branns førstelag gjennomførte en treningsøkt i Bergen.','Brann-spilleren som opprinnelig er fra Bømlo deltok på trening med laget sitt. Artikkelen handler om Branns fotballtrening og forberedelser. Bremnes IL er ikke involvert.','sport',['clubs'=>['Brann'],'places'=>['Bergen','Bømlo']]],
        ['politikk','politikk','Kommunestyret debatterer budsjettprioriteringer','Politikerne er uenige om fordelingen mellom skole og samferdsel.','Kommunestyret i Bømlo skal stemme over budsjettet. Debatten handler om politiske prioriteringer og ulike partiers forslag. Saken gjelder den politiske behandlingen og vedtaket.','politikk',['organizations'=>['Bømlo kommunestyre']]],
        ['tjeneste','kommunesaker','Nye åpningstider for kommunens servicetorg','Kommunens servicetorg får utvidede åpningstider fra neste uke.','Administrasjonen opplyser når innbyggerne kan møte opp, hvor de får hjelp til søknader og hvordan de kan kontakte tjenesten. Dette er praktisk tjenesteinformasjon uten politisk debatt.','kommunesaker',['organizations'=>['Bømlo kommune']]],
        ['konsert','kultur-arrangement','Konsertkveld på kulturhuset','Lokale musikere spiller konsert på scenen.','Publikum får oppleve sang, gitar og scenelys under en kulturkveld. Arrangøren beskriver konsertprogrammet og når dørene åpner.','kultur',['places'=>['Bømlo']]],
        ['idrett','sport','Åpen treningsdag for flere idrettslag','Flere klubber inviterer til felles trening med ball og styrkeøvelser.','Idrettsarrangementet lar barn og voksne prøve øvelser med fotball, basketball, treningskjegler og manualer. Målet er fysisk aktivitet på tvers av klubbene.','sport',[]],
        ['frisor',null,'Frisørsalong åpner på Svortland','En ny frisørsalong tilbyr hårklipp og hårfarging.','Salongen har frisørstoler, vaskestasjoner og et lite venteområde. Saken handler om frisørfaget og salongens tjenester, ikke verksted, industri, kystmiljø eller maritim virksomhet.','naeringsliv',['places'=>['Svortland']]],
        ['maritim','naeringsliv','Verksted bygger ut for maritime oppdrag','Et lokalt skipsverksted utvider arbeidet med mekanisk vedlikehold.','Fagarbeidere sveiser og bearbeider ståldeler til fartøy. Artikkelen handler om det maritime verkstedet, industriarbeidsplasser og håndverket i produksjonshallen.','naeringsliv',[]],
    ];
    $results=[];
    foreach($fixtures as[$name,$expected,$title,$intro,$body,$category,$entities]){
        $item=['title'=>$title,'category'=>$category,'entities'=>$entities,'web'=>['title'=>$title,'intro'=>$intro,'body'=>$body]];
        // Only these synthetic fixtures are logged; never log config or real editorial data.
        $request=static function(array $settings,array $payload)use($name):string{
            try{$raw=producer_request($settings,$payload);}
            catch(Throwable $error){echo json_encode(['fixture'=>$name,'request_error'=>$error->getMessage()],JSON_UNESCAPED_UNICODE)."\n";throw $error;}
            try{json_decode($raw,true,32,JSON_THROW_ON_ERROR);}
            catch(JsonException $error){echo json_encode(['fixture'=>$name,'invalid_json'=>$raw],JSON_UNESCAPED_UNICODE)."\n";throw $error;}
            return $raw;
        };
        $choice=studio_images_prepare($item,$config,$request,static fn()=>$catalog);
        $ok=$choice!==null&&($choice['theme_id']??null)===$expected&&$choice['status']===($expected===null?'INGEN_PASSENDE_BILDE':'selected');
        $results[]=['fixture'=>$name,'expected'=>$expected,'theme'=>$choice['theme_id']??null,'status'=>$choice['status']??null,'reason'=>$choice['reason']??'','passed'=>$ok];
        rrni_pilot_private_write(__DIR__.'/pilot.json',json_encode($results,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        echo json_encode(end($results),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
        rrni_pilot_assert($ok,'Live semantic pilot failed: '.$name.'; flag remains unchanged');
        $item['web']['imageSelection']=$choice;
        rrni_pilot_assert(studio_images_prepare($item,$config,static fn()=>throw new RuntimeException('Unexpected repeated model call'))===$choice,'Retry changed a saved choice');
    }
    // Confirm the real REST connection still returns precisely the same 21 media.
    rrni_pilot_assert(studio_images_catalog()===$catalog,'Live catalog changed during pilot');
    foreach($protected as$path=>$hash)rrni_pilot_assert(is_file($path)&&hash_file('sha256',$path)===$hash,'Studio data/config changed during pilot; investigate without restoring');
    rrni_pilot_assert((is_file($flag)?file_get_contents($flag):null)===$before,'Activation flag changed concurrently');
    rrni_pilot_private_write($flag,"<?php\nreturn true;\n");
    rrni_pilot_assert(studio_images_enabled(load_config()),'Activation readback failed');
    $result=['at'=>gmdate('c'),'studio_commit'=>$state['commit'],'enabled'=>true,'new_drafts_only'=>true,'model'=>$config['openai_model'],'live_model_cases'=>$results,
        'catalog_assets'=>count($catalog['assets']),'private_config_and_board_unchanged'=>true,'backup'=>$backup,'flag'=>$flag];
    rrni_pilot_private_write(__DIR__.'/result.json',json_encode($result,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    echo "NEWS_IMAGES_STUDIO_ACTIVE; live model cases=8; media=21; existing content unchanged\n";
}finally{flock($lock,LOCK_UN);fclose($lock);}
