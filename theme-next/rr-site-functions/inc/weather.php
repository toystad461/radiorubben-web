<?php
if (!defined('ABSPATH')) exit;
function rr_weather_places() {
 return ['rubbestadneset'=>['Rubbestadneset',59.8156,5.2680],'svortland'=>['Svortland',59.7928,5.1722],
 'mosterhamn'=>['Mosterhamn',59.6991,5.3858],'langevag'=>['Langevåg',59.6021,5.2081],
 'finnas'=>['Finnås',59.7395,5.2330],'brandasund'=>['Brandasund',59.8984,5.0919],
 'espevaer'=>['Espevær',59.5944,5.1566]];
}
function rr_weather_budget() {
 $key='rr_weather_budget_'.gmdate('YmdHi');
 $count=(int)get_transient($key);
 if($count>=40)return false;
 set_transient($key,$count+1,90);
 return true;
}
function rr_weather_search() {
 nocache_headers();
 $q=isset($_GET['q'])&&is_string($_GET['q'])?sanitize_text_field(wp_unslash($_GET['q'])):'';
 if(mb_strlen($q)<2||mb_strlen($q)>60)wp_send_json_error(['message'=>'Skriv mellom 2 og 60 tegn.'],400);
 $key='rr_place_search_'.md5(mb_strtolower($q));
 $found=get_transient($key);
 if(is_array($found))wp_send_json_success($found);
 if(!rr_weather_budget())wp_send_json_error(['message'=>'Prøv søket igjen om litt.'],429);
 $r=wp_remote_get('https://ws.geonorge.no/stedsnavn/v1/navn?'.http_build_query(['sok'=>$q,'treffPerSide'=>30]),['timeout'=>12,'headers'=>['User-Agent'=>'RadioRubben/1.1 https://www.radiorubben.no']]);
 if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)wp_send_json_error(['message'=>'Stedssøket er midlertidig utilgjengelig.'],503);
 $d=json_decode(wp_remote_retrieve_body($r),true);
 $found=[];$seen=[];
 foreach(($d['navn']??[]) as $row){
  $lat=$row['representasjonspunkt']['nord']??null;$lon=$row['representasjonspunkt']['øst']??null;
  if(!is_numeric($lat)||!is_numeric($lon))continue;
  $name=$row['skrivemåte']??'';
  $municipal=implode(', ',array_column($row['kommuner']??[],'kommunenavn'));
  $type=$row['navneobjekttype']??'';
  $id=$name.'|'.$municipal;
  if(isset($seen[$id]))continue;
  $seen[$id]=true;
  $found[]=['name'=>$name,'municipality'=>$municipal,'type'=>$type,'lat'=>round((float)$lat,3),'lon'=>round((float)$lon,3)];
 }
 set_transient($key,$found,DAY_IN_SECONDS);
 wp_send_json_success($found);
}
add_action('wp_ajax_rr_weather_search','rr_weather_search');
add_action('wp_ajax_nopriv_rr_weather_search','rr_weather_search');

function rr_weather_data($place) {
    $places=rr_weather_places();
    if(is_array($place)) {
      $p=$place;
      if(!isset($p[1],$p[2])||!is_numeric($p[1])||!is_numeric($p[2])||$p[1]<57||$p[1]>81||$p[2]<3||$p[2]>35)return new WP_Error('place','Velg et sted i Norge.');
      $p[1]=round((float)$p[1],3);$p[2]=round((float)$p[2],3);
    } else {
      if(!isset($places[$place]))return new WP_Error('place','Ukjent sted.');
      $p=$places[$place];
    }
    $key='rr_weather_v2_'.md5($p[1].','.$p[2]);
    $cache = get_transient($key);
    if (is_array($cache) && ($cache['expires'] ?? 0) > time()) return $cache;
    if (get_transient($key.'_lock')) return is_array($cache) ? $cache : new WP_Error('busy','Værvarselet oppdateres. Prøv igjen om litt.');
    set_transient($key.'_lock',1,60);
    $headers = ['User-Agent'=>'RadioRubben/1.0 https://www.radiorubben.no post@radiorubben.no'];
    if (!empty($cache['modified'])) $headers['If-Modified-Since']=$cache['modified'];
    if(!rr_weather_budget())return is_array($cache)?$cache:new WP_Error('busy','Prøv igjen om litt.');
    $r=wp_remote_get('https://api.met.no/weatherapi/locationforecast/2.0/compact?lat='.$p[1].'&lon='.$p[2],['timeout'=>12,'headers'=>$headers]);
    $code=is_wp_error($r)?0:wp_remote_retrieve_response_code($r);
    $expiry=is_wp_error($r)?0:strtotime(wp_remote_retrieve_header($r,'expires'));
    $expires=max(time()+300,$expiry ?: time()+1800);
    if ($code===304 && is_array($cache)) {
        $cache['expires']=$expires;
        set_transient($key,$cache,DAY_IN_SECONDS);
        return $cache;
    }
    if ($code===200 || $code===203) {
        $json=json_decode(wp_remote_retrieve_body($r),true);
        $series=$json['properties']['timeseries']??[];
        if (is_array($series) && count($series)) {
            $cache=['place'=>$p[0],'expires'=>$expires,'modified'=>wp_remote_retrieve_header($r,'last-modified'),'updated'=>$json['properties']['meta']['updated_at']??gmdate('c'),'hours'=>[]];
            foreach ($series as $s) {
                $d=$s['data']['instant']['details']??[];
                if (!isset($s['time'],$d['air_temperature'])) continue;
                $n=$s['data']['next_1_hours']??null;
                $symbol=$n['summary']['symbol_code']??$s['data']['next_6_hours']['summary']['symbol_code']??'';
                $cache['hours'][]=['time'=>$s['time'],'temp'=>$d['air_temperature'],'wind'=>$d['wind_speed']??null,'rain'=>$n['details']['precipitation_amount']??null,'symbol'=>$symbol];
            }
            set_transient($key,$cache,DAY_IN_SECONDS);
            return $cache;
        }
    }
    set_transient($key.'_lock',1,600);
    return is_array($cache)?$cache:new WP_Error('unavailable','Værvarselet er midlertidig utilgjengelig.');
}
function rr_weather_ajax() {
    nocache_headers();
    $place=isset($_GET['place'])&&is_string($_GET['place'])?sanitize_key(wp_unslash($_GET['place'])):'rubbestadneset';
    if(isset($_GET['lat'],$_GET['lon'])) {
      $place=['Valgt sted',$_GET['lat'],$_GET['lon']];
    }
    $d=rr_weather_data($place);
    if (is_wp_error($d)) wp_send_json_error(['message'=>$d->get_error_message()],503);
    unset($d['modified'],$d['expires']);
    wp_send_json_success($d);
}
add_action('wp_ajax_rr_weather','rr_weather_ajax');
add_action('wp_ajax_nopriv_rr_weather','rr_weather_ajax');
add_action('wp_enqueue_scripts',function(){
    if (!is_front_page()) return;
    wp_enqueue_style('rr-weather',rtrim(RR_SITE_URL, '/').'/assets/css/weather.css',[],'1.1.0');
    wp_enqueue_script('rr-weather',rtrim(RR_SITE_URL, '/').'/assets/js/weather.js',[],'1.1.2',true);
});
function rr_weather_card() {
    $url=admin_url('admin-ajax.php');
    if (isset($_GET['wpvibe_preview'])) $url=add_query_arg('wpvibe_preview',sanitize_text_field(wp_unslash($_GET['wpvibe_preview'])),$url);
    ?>
    <section class="rr-wrap rr-weather" aria-labelledby="rr-weather-title" data-endpoint="<?php echo esc_url($url); ?>">
      <div class="rr-weather-head"><div><p class="rr-eyebrow">HER DU ER</p><h2 id="rr-weather-title">Været</h2></div><label>Velg sted <select class="rr-weather-place"><?php foreach(rr_weather_places() as $id=>$place): ?><option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($place[0]); ?></option><?php endforeach; ?><option value="custom" hidden>Mitt valgte sted</option></select></label></div>
      <details class="rr-weather-location"><summary>Søk sted eller bruk posisjon</summary>
      <form class="rr-weather-search"><label for="rr-place-query">Finn ditt sted i Norge</label><div class="rr-search-row"><input id="rr-place-query" type="search" minlength="2" maxlength="60" placeholder="Stedsnavn, for eksempel Tromsø" required/><button type="submit">Søk</button><button type="button" class="rr-weather-geolocate">Bruk min posisjon</button></div></form>
      <p class="rr-search-status" role="status"></p><ul class="rr-place-results" aria-label="Søkeresultater"></ul>
      <p class="rr-weather-small">Stedsvalget huskes bare i denne nettleseren. <button type="button" class="rr-weather-reset">Tilbake til Rubbestadneset</button></p>
      </details>
      <p class="rr-weather-status" role="status">Henter værvarselet …</p>
      <div class="rr-weather-result" hidden></div>
      <p class="rr-weather-credit">Værdata: <a href="https://www.met.no/">Meteorologisk institutt</a> · <a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</a>. Avrundet og presentert av Radio Rubben. Stedsnavn: <a href="https://www.kartverket.no/">Kartverket</a>.</p>
      <noscript><p>Slå på JavaScript for å vise varselet, eller <a href="https://www.yr.no/">se været på Yr</a>.</p></noscript>
    </section>
    <?php
}
