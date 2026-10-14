<?php
/** Pure policy checks; WordPress owns attachment identity and file access. */
const RRMP_VERSION = '1.0.0';
const RRMP_META = '_rr_media_policy';
function rrmp_label(string $origin): string {
    return ['ai_generated'=>'AI-generert illustrasjon','ai_edited'=>'AI-redigert bilde'][$origin] ?? '';
}
function rrmp_fingerprint(array $record): string {
    unset($record['approval'], $record['history']);
    return hash('sha256', json_encode($record, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}
function rrmp_record(array $input, array $files, array $previous, int $actor, bool $approve, array $presentation=[]): array {
    $origin=(string)($input['origin']??'unknown');
    if(!in_array($origin,['unknown','photo','illustration','ai_generated','ai_edited'],true))throw new InvalidArgumentException('Velg et gyldig bildeopphav.');
    $r=['version'=>RRMP_VERSION,'origin'=>$origin,'generator'=>trim((string)($input['generator']??'')),
        'producedOn'=>trim((string)($input['producedOn']??'')),'reference'=>trim((string)($input['reference']??'')),
        'description'=>trim((string)($input['description']??'')),'files'=>$files,'presentation'=>$presentation,
        'previousLabel'=>$previous['previousLabel']??rrmp_label($previous['origin']??''),
        'revision'=>(int)($previous['revision']??0)+1,'approval'=>null];
    foreach(['generator','reference','description']as$key)if(strlen($r[$key])>2000)throw new InvalidArgumentException('Metadatafeltet er for langt.');
    $date=$r['producedOn'];
    if($date!==''&&(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||!checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))||$date>gmdate('Y-m-d')))throw new InvalidArgumentException('Oppgi en gyldig produksjonsdato.');
    if(rrmp_label($origin)!=='')$r['previousLabel']=rrmp_label($origin);
    if($approve){
        if($actor<=0||$origin==='unknown'||!$files||$r['reference']==='')throw new InvalidArgumentException('Avklar opphav, fil og kilde/rettighetsgrunnlag før godkjenning.');
        if(rrmp_label($origin)!==''&&($date===''||$r['generator']===''||($origin==='ai_edited'&&$r['description']==='')))throw new InvalidArgumentException('Oppgi generator (eller uttrykkelig «Ukjent»), produksjonsdato og hva som er AI-redigert.');
        if(!rrmp_label($origin)&&($previous['previousLabel']??rrmp_label($previous['origin']??''))!==''&&trim((string)($input['correction']??''))==='')throw new InvalidArgumentException('Begrunn rettelsen når tidligere AI-opphav endres.');
        $r['previousLabel']=rrmp_label($origin);
        $r['approval']=['hash'=>rrmp_fingerprint($r),'actor'=>$actor,'at'=>gmdate('c')];
    }
    $r['history']=$previous['history']??[];
    if($previous){unset($previous['history']);$r['history'][]=['previous'=>$previous,'actor'=>$actor,'at'=>gmdate('c'),'reason'=>trim((string)($input['correction']??''))];}
    return $r;
}
function rrmp_current(array $record,array $files,array $presentation=[]): bool {
    return ($record['version']??'')===RRMP_VERSION&&($record['origin']??'unknown')!=='unknown'
        &&$files&&($record['files']??null)===$files&&($record['presentation']??null)===$presentation&&($record['approval']['actor']??0)>0
        &&!empty($record['approval']['at'])&&hash_equals(rrmp_fingerprint($record),(string)($record['approval']['hash']??''));
}
function rrmp_public_label(array $record): string {
    return rrmp_label($record['origin']??'') ?: (string)($record['previousLabel']??'');
}
