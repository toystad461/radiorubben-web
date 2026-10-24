<?php
namespace RadioRubben\Fotballrobot;

/** Two distinct weekly products, using the existing source adapter and approval desk. */
final class WeeklyCoverage {
    const HOOK='rrfr_club_results';
    public static function week(string $start): array {
        $tz=new \DateTimeZone('Europe/Oslo');
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$start,$tz);
        if(!$d||$d->format('Y-m-d')!==$start||$d->format('N')!=='1')throw new \RuntimeException('Velg mandagen i en gyldig uke.');
        return ['key'=>$start,'start'=>$d->format(DATE_ATOM),'end'=>$d->modify('+1 week')->format(DATE_ATOM)];
    }
    public static function previousWeek(int $now): array {
        $d=(new \DateTimeImmutable('@'.$now))->setTimezone(new \DateTimeZone('Europe/Oslo'));
        return self::week($d->modify('monday this week')->modify('-1 week')->format('Y-m-d'));
    }
    public static function nextRun(int $now): int {
        $d=(new \DateTimeImmutable('@'.$now))->setTimezone(new \DateTimeZone('Europe/Oslo'));
        $next=$d->modify('monday this week')->setTime(8,0);
        if($next->getTimestamp()<=$now)$next=$next->modify('+1 week');
        return $next->getTimestamp();
    }
    public static function register(): void {
        if(ClubAutomation::active()&&!wp_next_scheduled(self::HOOK))wp_schedule_single_event(self::nextRun(time()),self::HOOK);
    }
    public static function stop(): void {wp_clear_scheduled_hook(self::HOOK);}
    public static function facts(array $feed,array $week,string $kind): array {
        if(!in_array($kind,['preview','results'],true))throw new \RuntimeException('Ukjent ukesartikkel.');
        $matches=ClubCoverage::weekMatches($feed['matches'],$week);$ready=[];$unresolved=[];
        foreach($matches as $m){
            if($kind==='preview'||(($m['finished_confirmed']??false)===true&&is_array($m['score']??null)))$ready[]=$m;
            else $unresolved[]=$m;
        }
        $facts=array_replace(ClubAutomation::weeklyFacts($ready,$week,$feed['fetched_at']),['kind'=>$kind,'unresolved'=>$unresolved]);
        foreach($unresolved as $m)$facts['sources'][]=['url'=>$m['source'],'provider'=>'Fotballdata','fetched_at'=>$feed['fetched_at']];
        return $facts;
    }
    public static function period(array $week): string {
        $tz=new \DateTimeZone('Europe/Oslo');
        return wp_date('j. F',strtotime($week['start']),$tz).'–'.wp_date('j. F Y',strtotime($week['end'])-1,$tz);
    }
    public static function validLogo($url): bool {
        return is_string($url)&&preg_match('~^https://(?:images\.fotball\.no/clublogos/[a-zA-Z0-9._/-]+|logo\.fotballdata\.no/logos/[0-9]+\.(?:jpg|png)(?:\?w=[0-9]{1,4})?)$~D',$url)===1;
    }
    public static function styles(): void {
        if(!is_singular('post'))return;
        $facts=get_post_meta(get_queried_object_id(),'_rrfr_fact_snapshot',true);
        if(!is_array($facts)||(!isset($facts['week'])&&!isset($facts['match'])))return;
        wp_enqueue_script('rrfr-week-logos',plugins_url('weekly-coverage.js',__FILE__),[],(string)filemtime(__DIR__.'/weekly-coverage.js'),true);
        self::stylesheet();
    }
    /** The same markup is stored in drafts and shown in both reading surfaces. */
    public static function card(array $m,bool $results=true,bool $linkable=false): string {
        $name=($m['home']['registered_name']??$m['home']['name']).' – '.($m['away']['registered_name']??$m['away']['name']);
        $attrs=$linkable&&$results?' data-rrfr-match="'.(int)$m['id'].'" data-rrfr-evidence="'.MatchStories::identity($m).'"':'';
        $html='<section class="rrfr-week-card"'.$attrs.'><p class="rrfr-week-competition">'.esc_html($m['competition']['name']).'</p><h2 class="rrfr-match-heading">'.esc_html($name).'</h2><div class="rrfr-match-layout">';
        foreach(['home','away'] as $i=>$side){
            if($i===1)$html.='<p class="rrfr-week-score"><span>'.($results?esc_html($m['score'][0].'–'.$m['score'][1]):'–').'</span><small>'.($results?'Sluttresultat':'Mot').'</small></p>';
            $html.='<div class="rrfr-week-team">';$logo=$m['logos'][$side]??'';
            if(self::validLogo($logo))$html.='<img src="'.esc_url($logo).'" alt="" width="40" height="40" loading="lazy" decoding="async">';
            $html.='<span>'.esc_html($m[$side]['registered_name']??$m[$side]['name']).'</span></div>';
        }
        $html.='</div><p class="rrfr-week-time">'.esc_html(wp_date('l j. F \k\l. H:i',strtotime($m['kickoff']),new \DateTimeZone('Europe/Oslo'))).'</p><p class="rrfr-week-venue">'.esc_html($m['venue']).'</p>';
        return $html.'<p class="rrfr-week-source"><small><a href="'.esc_url($m['source']).'">Kampinformasjon hos NFF</a></small></p></section>';
    }
    public static function stylesheet(): void {
        echo '<style id="rrfr-week-style">'.file_get_contents(__DIR__.'/weekly-coverage.css').'</style>';
    }
    public static function rows(array $facts): string {
        $tz=new \DateTimeZone('Europe/Oslo');$html='';$results=($facts['kind']??'preview')==='results';
        foreach($facts['matches'] as $m)$html.=self::card($m,$results,true);
        if(!empty($facts['unresolved'])){
            $html.='<h2>Resultat ikke bekreftet</h2><p>Disse registrerte kampene er ikke tatt med i resultatoppsummeringen:</p><ul>';
            foreach($facts['unresolved'] as $m)$html.='<li><small><a href="'.esc_url($m['source']).'">'.esc_html(($m['home']['registered_name']??$m['home']['name']).' – '.($m['away']['registered_name']??$m['away']['name'])).'</a></small></li>';
            $html.='</ul>';
        }
        return $html.'<p><small>Kilde: Fotballdata og lenkede kampregistreringer hos NFF. Grunnlag hentet '.esc_html(wp_date('d.m.Y H:i',strtotime($facts['fetched_at']),$tz)).'. Avlyste, utsatte, avbrutte og walkover-kamper er utelatt.</small></p>';
    }
    public static function article(array $facts): array {
        $results=$facts['kind']==='results';$period=self::period($facts['week']);$count=count($facts['matches']);
        $a=['title'=>($results?'Slik gikk Bremnes-kampene ':'Bremnes-kamper ').$period,
            'lead'=>$results?'Her er resultatene fra '.$count.' Bremnes-kamper fra G13/J13 og oppover i perioden '.$period.'.':'Bremnes har '.$count.' registrerte kamper fra G13/J13 og oppover i perioden '.$period.'. Her er oversikten over hjemme- og bortekampene.',
            'paragraphs'=>[$results?'Oversikten omfatter bare bekreftede sluttresultater i det kontrollerte kampgrunnlaget.':'Kampoppsettet kan endres. Oversikten bygger på registrerte kamper hos Fotballdata.'],
            'inline_sources'=>[],'checks'=>[['claim'=>'Periode, antall og bekreftede resultater','support'=>'week, matches og unresolved i faktagrunnlaget']]];
        if(!$count)$a['lead']='Det er ingen registrerte '.($results?'bekreftede resultater':'Bremnes-kamper').' fra G13/J13 og oppover i perioden '.$period.' i det kontrollerte kampgrunnlaget.';
        $q=Writer::qualityReview($a,$facts);$a=$q['article'];
        return ClubAutomation::reviewedPost($a,Writer::body($a,$facts).self::rows($facts),$q);
    }
    public static function prepare(string $kind,string $start): int {
        if(!Robot::allowed()||!current_user_can('publish_posts'))throw new \RuntimeException('Ingen tilgang.');
        $week=self::week($start);$now=time();
        if(!in_array($kind,['preview','results'],true)||strtotime($week['start'])<$now-28*DAY_IN_SECONDS||strtotime($week['start'])>$now+8*DAY_IN_SECONDS)
            throw new \RuntimeException('Uken ligger utenfor perioden som kan klargjøres.');
        if($kind==='results'&&strtotime($week['end'])>$now)throw new \RuntimeException('Resultatoppsummeringen krever en avsluttet uke.');
        if($kind==='preview'&&strtotime($week['end'])<=$now)throw new \RuntimeException('Kampoversikten er utløpt. Lag en resultatoppsummering.');
        $key=($kind==='results'?'results:':'week:').$start;
        if($id=ClubAutomation::existing($key))return $id;
        $facts=self::facts(ClubCoverage::collect(),$week,$kind);
        if(!$facts['matches']&&!$facts['unresolved'])throw new \RuntimeException('Ingen registrerte kamper i perioden.');
        return ClubAutomation::create($key,$facts,static fn()=>self::article($facts));
    }
    public static function tick(): void {
        if(!ClubAutomation::active())return;
        $previous=get_current_user_id();
        try{
            $owner=(int)get_option('rrfr_club_owner',0);
            if(!$owner||!user_can($owner,'manage_options')||!user_can($owner,'publish_posts'))throw new \RuntimeException('Automatikken mangler en aktiv redaktør.');
            wp_set_current_user($owner);
            self::prepare('results',self::previousWeek(time())['key']);
            delete_option('rrfr_club_error_results');
        }catch(\Throwable $e){ClubAutomation::error('results',$e);}
        finally{wp_set_current_user($previous);self::register();}
    }
    public static function routes(): void {
        MatchStories::routes();
        register_rest_route('rr-fotballrobot/v1','/weekly',[
            'methods'=>'POST','permission_callback'=>[Robot::class,'allowed'],
            'callback'=>static fn($r)=>Robot::response(static function()use($r){
                $id=self::prepare((string)$r->get_param('kind'),(string)$r->get_param('week'));
                return ['id'=>$id,'status'=>get_post($id)->post_status,'review_url'=>PlayerReview::url($id)];
            })
        ]);
    }
}

require_once __DIR__.'/match-stories.php';
