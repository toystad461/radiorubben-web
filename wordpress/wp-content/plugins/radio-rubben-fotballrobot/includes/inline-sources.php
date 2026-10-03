<?php
namespace RadioRubben\Fotballrobot;

/** Validated citations become HTML only after writing and editorial review. No network or writes. */
final class InlineSources {
    public static function prompt(): string {
        return 'KILDELENKER I SETNINGENE: Bruk inline_sources til å gjøre 1–3 relevante opplysninger klikkbare når faktapakken har en konkret kilde som støtter dem. Velg naturlig, beskrivende tekst, for eksempel «gult kort i det 59. minutt», ikke «klikk her». paragraph=-1 betyr ingress; 0 er første brødtekstavsnitt. text må være et ordrett, entydig utdrag fra akkurat det avsnittet. source_url kopieres nøyaktig fra den tilhørende kilden i facts; aldri gjett URL, anker eller kildekobling. Lenke til kampsiden ved kamphendelser, spillerprofilen ved sesongstatistikk og den konkrete avis-/klubbartikkelen ved opplysninger derfra. Ikke legg lenker i tittelen eller gjenta samme lenke i hvert avsnitt. Bruk [] når ingen sikker kobling finnes. Ingen HTML eller Markdown i teksten. Ved språkvask: bevar lenkene hvis teksten står uendret; oppdater utdrag og avsnittsnummer når teksten endres, uten å bytte kilde uten faktastøtte. Systemet bygger lenkene og beholder kildelisten nederst.';
    }
    public static function schema(): array {
        return ['type'=>'array','maxItems'=>4,'items'=>['type'=>'object','additionalProperties'=>false,'properties'=>[
            'paragraph'=>['type'=>'integer','minimum'=>-1,'maximum'=>7],
            'text'=>['type'=>'string'],'source_url'=>['type'=>'string']
        ],'required'=>['paragraph','text','source_url']]];
    }
    private static function safe(string $url): bool {
        $u=parse_url($url);
        return $u && ($u['scheme']??'')==='https'
            && !isset($u['user']) && !isset($u['pass']) && !isset($u['port']) && !isset($u['fragment'])
            && preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z]{2,}$/i',$u['host']??'')
            && !preg_match('/[\\x00-\\x20\\x7f<>"\\\\]/',$url);
    }
    /** Only source fields with known provenance, never arbitrary strings in source prose. */
    public static function urls(array $facts): array {
        $urls=[$facts['source']??null,$facts['match']['source']??null];
        if(($facts['type']??'')==='public_news')$urls[]=$facts['news']['url']??null;
        foreach($facts['sources']??[] as $s)if(is_array($s))$urls[]=$s['url']??null;
        foreach($facts['events']??[] as $e)if(is_array($e)){
            $urls[]=$e['source']??null;
            if(is_array($e['after']??null))$urls[]=$e['after']['source']??null;
        }
        foreach(['home','away'] as $side){
            foreach($facts['forms'][$side]['last_five']??[] as $m)$urls[]=$m['source']??null;
            $urls[]=$facts['forms'][$side]['next']['source']??null;
        }
        return array_values(array_unique(array_filter($urls,static fn($u)=>is_string($u)&&self::safe($u))));
    }
    /** Optional for legacy articles. Invalid or ambiguous spans fail closed. */
    public static function validate(array $article,?array $facts=null): void {
        if(!array_key_exists('inline_sources',$article))return;
        $links=$article['inline_sources'];
        if(!is_array($links)||!array_is_list($links)||count($links)>4)throw new \RuntimeException('Ugyldige kildelenker.');
        $used=[];$urls=$facts===null?null:self::urls($facts);
        foreach($links as $link){
            if(!is_array($link)||count($link)!==3||!isset($link['paragraph'],$link['text'],$link['source_url'])
                ||!is_int($link['paragraph'])||$link['paragraph']< -1||$link['paragraph']>7
                ||!is_string($link['text'])||!is_string($link['source_url']))throw new \RuntimeException('Ugyldig kildelenkeformat.');
            $index=$link['paragraph'];$text=$index===-1?($article['lead']??null):($article['paragraphs'][$index]??null);
            $span=$link['text'];$url=$link['source_url'];
            if(!is_string($text)||trim($span)!==$span||mb_strlen($span)<6||mb_strlen($span)>220
                ||preg_match('/<[^>]*>|https?:\\/\\//i',$span)||substr_count($text,$span)!==1
                ||!self::safe($url)||($urls!==null&&!in_array($url,$urls,true)))
                throw new \RuntimeException('Kildelenken mangler entydig tekst eller en kontrollert kilde i faktagrunnlaget.');
            $start=strpos($text,$span);$end=$start+strlen($span);
            foreach($used[$index]??[] as [$a,$b])if($start<$b&&$end>$a)throw new \RuntimeException('Kildelenkene overlapper.');
            $used[$index][]=[$start,$end];
        }
    }
    public static function paragraphs(array $article,array $facts): array {
        self::validate($article,$facts);
        $links=[];foreach($article['inline_sources']??[] as $l)$links[$l['paragraph']][]=$l;
        $out=[];
        foreach(array_merge([$article['lead']],$article['paragraphs']) as $i=>$text){
            $spans=$links[$i-1]??[];
            usort($spans,static fn($a,$b)=>strpos($text,$a['text'])<=>strpos($text,$b['text']));
            $rendered='';$cursor=0;
            foreach($spans as $link){
                $start=strpos($text,$link['text']);
                $rendered.=esc_html(substr($text,$cursor,$start-$cursor)).'<a href="'.htmlspecialchars($link['source_url'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'">'.esc_html($link['text']).'</a>';
                $cursor=$start+strlen($link['text']);
            }
            $out[]=$rendered.esc_html(substr($text,$cursor));
        }
        return $out;
    }
    /** Preserve body link targets when reviewing human-edited HTML; footer links are separate. */
    public static function fromHtml(string $html): array {
        $doc=new \DOMDocument();
        $old=libxml_use_internal_errors(true);
        try {$doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
        finally {libxml_clear_errors();libxml_use_internal_errors($old);}
        $links=[];
        foreach((new \DOMXPath($doc))->query('//a[@href][not(ancestor::small)]') as $a)
            $links[]=['paragraph'=>0,'text'=>trim($a->textContent),'source_url'=>$a->getAttribute('href')];
        return $links;
    }
}
