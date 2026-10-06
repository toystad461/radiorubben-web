<?php
namespace RadioRubben\Fotballrobot;

/** Bounded, attributed evidence for original articles. Never downloads or embeds third-party video. */
final class MediaSources {
    public static function facts($values): array {
        if(!is_array($values)||!array_is_list($values)||count($values)<1||count($values)>6)throw new \RuntimeException('Oppgi ett til seks fakta per kilde.');
        foreach($values as $v)if(!is_string($v)||trim($v)===''||mb_strlen($v)>350||$v!==strip_tags($v))throw new \RuntimeException('Fakta må være korte punkter i egne ord, uten HTML.');
        $values=array_map('trim',$values);
        if(count(preg_split('/\s+/u',implode(' ',$values),-1,PREG_SPLIT_NO_EMPTY))>120)throw new \RuntimeException('Bruk høyst 120 ord med fakta i egne ord fra hver kilde.');
        return $values;
    }
    public static function validate(array $input,string $primary): array {
        $format=$input['format']??'brief';
        if(!in_array($format,['brief','feature'],true))throw new \RuntimeException('Ukjent artikkelformat.');
        $sources=$input['supporting_sources']??[];
        if(!is_array($sources)||!array_is_list($sources)||count($sources)>5)throw new \RuntimeException('Bruk høyst fem supplerende kilder.');
        $clean=[];$urls=[$primary];$types=[];
        foreach($sources as $s){
            if(!is_array($s)||($s['public_read']??false)!==true||!in_array($s['kind']??'', ['match','background','table','club'],true))throw new \RuntimeException('Supplerende kilder må være lest og merket med hva de dokumenterer.');
            $url=PlayerMonitor::url((string)($s['url']??''));
            if(in_array($url,$urls,true))throw new \RuntimeException('Samme kilde skal bare legges inn én gang.');
            $title=$s['title']??null;
            if(!is_string($title)||trim($title)===''||mb_strlen($title)>200||$title!==strip_tags($title))throw new \RuntimeException('Kilden trenger en kort tittel.');
            $date=$s['event_date']??null;
            if($date!==null&&(!is_string($date)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!strtotime($date)||gmdate('Y-m-d',strtotime($date))!==$date))throw new \RuntimeException('Oppgi kildens hendelsesdato eller null.');
            $clean[]=['kind'=>$s['kind'],'url'=>$url,'title'=>trim($title),'facts'=>self::facts($s['facts']??null),'checked_at'=>PlayerMonitor::checked($s['checked_at']??null),'event_date'=>$date];
            $urls[]=$url;$types[$s['kind']]=true;
        }
        $video=$input['video']??null;
        if($video!==null){
            if(!is_array($video)||($video['content_verified']??false)!==true)throw new \RuntimeException('Videoinnholdet må være kontrollert. Et søketreff eller en tittel er ikke et intervju.');
            $url=PlayerMonitor::url((string)($video['url']??''));$publisher=$video['publisher']??null;
            if($url!==$primary||!is_string($publisher)||trim($publisher)===''||mb_strlen($publisher)>100||$publisher!==strip_tags($publisher))throw new \RuntimeException('Bruk mediets originale side med intervjuet som hovedkilde og oppgi mediets navn.');
            $video=['url'=>$url,'publisher'=>trim($publisher),'content_verified'=>true];
        }
        if($format==='feature'){
            $domains=array_unique(array_map(static fn($u)=>preg_replace('/^www\./','',parse_url($u,PHP_URL_HOST)),$urls));
            if(!$video||!isset($types['match'],$types['background'])||count($domains)<2)throw new \RuntimeException('En fordypningssak krever kontrollert intervju, egen kampkilde og egen bakgrunnskilde fra minst to nettsteder.');
            $count=count($input['facts']??[]);foreach($clean as $s)$count+=count($s['facts']);
            if($count<6)throw new \RuntimeException('Finn mer bekreftet bakgrunn før en fordypningssak skrives.');
        }
        return ['format'=>$format,'supporting_sources'=>$clean,'video'=>$video];
    }
    public static function videoBlock(array $facts): string {
        $v=$facts['news']['video']??null;
        if(($facts['type']??'')!=='public_news'||!is_array($v)||($v['content_verified']??false)!==true)return '';
        if(!in_array($v['url']??'',InlineSources::urls($facts),true))throw new \RuntimeException('Videoen mangler kontrollert kildelenke.');
        return '<a href="'.esc_url($v['url']).'">Se hele videointervjuet hos '.esc_html($v['publisher']).'</a>.';
    }
    public static function prompt(): string {
        return 'MEDIESAKER OG VIDEO: news.facts er opplysninger fra hovedkilden; hvert punkt i news.supporting_sources tilhører bare den oppgitte URL-en. Skriv selvstendig, med tydelig attribusjon til navngitt medium. Ikke lag direkte sitater; gjengi kontrollerte uttalelser kort i egne ord. Ingen kilde skal gjenfortelles uttømmende. Maksimalt 120 ord i artikkelen kan bygge på én mediekilde. Video er bare bekreftet når news.video.content_verified=true. Originalvideoen lenkes av systemet hos mediet; ikke påstå at Radio Rubben har intervjuet spilleren. Ikke skriv om bevegelser, tonefall eller annet videoinnhold som ikke er dokumentert i fakta. Ved news.format=feature: bruk normalt 4–7 avsnitt og 250–400 ord når grunnlaget bærer det; ellers skriv kortere og presist. Dette erstatter normalnotisens lengde. Bygg rundt nyheten, relevante poenger fra intervjuet, dokumentert kampbakgrunn og spillerens dokumenterte Bømlo-historikk. Ikke gjenta samme opplysning for å nå lengden. Bruk 2–4 naturlige inline_sources med presis kobling til kildene; systemet lenker også originalvideoen og alle kildene nederst. Påstanden serieleder krever dokumentert tabellplass FØR den aktuelle kampen, ikke dagens tabell. Skill kampdato, publiseringsdato, overgangsdato og oppdagelsestidspunkt. Dokumenterte fakta går foran lengdeønsket.';
    }
}
