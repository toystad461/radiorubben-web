<?php
namespace RadioRubben\Fotballrobot;

/** One digest for all new ready proposals. Never sends an article's title/body individually. */
final class ReviewDigest {
    const OPTION='rrfr_review_digest';
    const URL='https://studio.radiorubben.no/newsdesk.php';
    const DELAY=600;
    const INTERVAL=3600;
    public static function enqueue(int $id): void {
        $s=PlayerReview::state($id);
        if($s['status']!=='pending'||in_array($s['mail']??'',['accepted','sending','queued'],true))return;
        if(!empty($s['test'])||empty($s['notify']))return;
        $s['mail']='queued';$s['mail_queued_at']=gmdate(DATE_ATOM);update_post_meta($id,PlayerReview::META,$s);
    }
    /** Pure debounce/rate-limit policy. Accepted revisions are never re-notified. */
    public static function decision(array $state,array $keys,int $now): array {
        $keys=array_values(array_unique($keys));sort($keys);$new=array_values(array_diff($keys,$state['seen']??[]));
        if(!$new){unset($state['pending_since']);return ['state'=>$state,'send'=>false,'new'=>[],'count'=>count($keys)];}
        $state['pending_since']??=$now;
        $blocked=in_array($state['status']??'',['sending','uncertain'],true);
        $send=!$blocked&&$now-$state['pending_since']>=self::DELAY&&$now-(int)($state['accepted_at']??0)>=self::INTERVAL;
        return ['state'=>$state,'send'=>$send,'new'=>$new,'count'=>count($keys)];
    }
    public static function message(int $count): array {
        return ['subject'=>'Radio Rubben: '.$count.' '.($count===1?'sak':'saker').' til godkjenning',
            'body'=>"Det ligger ".$count.' '.($count===1?'ferdig sak':'ferdige saker')." til din godkjenning i nyhetsdesken.\n\nÅpne køen, les sakene og velg publiser, endringer eller forkast:\n".self::URL."\n\nLenken åpner desken og publiserer ingenting.\n\nRadio Rubben – Nyhetsdesken"];
    }
    public static function status(): array {
        $s=get_option(self::OPTION,[]);
        $message=in_array($s['status']??'',['sending','uncertain'],true)?'Siste e-postsending må kontrolleres. Ingen automatisk gjentakelse.':'Samlevarsel: tidligst etter ti minutter, høyst én gang per time når nye saker er klare.';
        $error=get_option('rrfr_newsroom_job_error','');if($error)$message.=' '.$error;
        if(!get_option('rrfr_newsroom_enabled',false))$message='Samlevarsler er ikke aktivert.';
        if(($lock=get_option('rrfr_digest_lock',0))&&$lock<time()-600)$message.=' Varslingsjobben er avbrutt og må kontrolleres.';
        return ['mode'=>'digest','status'=>$s['status']??'idle','acceptedAt'=>isset($s['accepted_at'])?gmdate(DATE_ATOM,$s['accepted_at']):null,'message'=>$message];
    }
    public static function worker(string $mode): array {
        if(!in_array($mode,['tick','queue'],true))throw new \RuntimeException('Ugyldig jobb.');
        $file=ABSPATH.'studio-private/app/newsroom-worker.php';$php=PHP_BINDIR.'/php';
        if(!is_file($file)||!is_executable($php)||!function_exists('proc_open'))throw new \RuntimeException('Studio-jobben er ikke tilgjengelig.');
        $process=proc_open([$php,$file,$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new \RuntimeException('Studio-jobben kunne ikke starte.');
        fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);$out='';$start=time();$exit=-1;
        try{
            while(true){$out.=stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);$status=proc_get_status($process);
                if(strlen($out)>1000000||time()-$start>150){proc_terminate($process);throw new \RuntimeException('Studio-jobben ble avbrutt.');}
                if(!$status['running']){$exit=$status['exitcode'];break;}usleep(100000);
            }
            $out.=stream_get_contents($pipes[1]);
        }finally{fclose($pipes[1]);fclose($pipes[2]);proc_close($process);}
        $result=json_decode($out,true);
        if($exit!==0||!is_array($result))throw new \RuntimeException('Studio-jobben må kontrolleres i nyhetsdesken.');
        return $result;
    }
    public static function tick(): void {
        if(!get_option('rrfr_newsroom_enabled',false))return;
        if(!add_option('rrfr_digest_lock',time(),'','no'))return;
        try{
            $keys=[];$ids=[];
            foreach(ReviewDesk::items() as $p){
                $s=get_post_meta($p->ID,PlayerReview::META,true);
                $isPlayer=is_array($s)&&!empty($s);
                if(!$isPlayer)$s=get_post_meta($p->ID,ReviewDesk::META,true)?:[];
                if(!empty($s['test'])||get_post_meta($p->ID,'_rrfr_test_only',true)||get_post_meta($p->ID,'_rrfr_trial_match',true)||($isPlayer&&empty($s['notify']))||($s['status']??'pending')!=='pending'||$p->post_status!=='draft'||!PublicationGate::current($p->ID,$p))continue;
                $key='wp:'.$p->ID.':'.PlayerReview::hash($p);$keys[]=$key;$ids[$key]=$p->ID;
            }
            $studio=self::worker('queue');if(($studio['version']??'')!=='2026-10-04.1')throw new \RuntimeException('Studio-køens versjon er ukjent.');
            foreach($studio['items']??[] as $row)if(is_string($row['key']??null)&&preg_match('/^studio:[a-f0-9]{16}:[a-f0-9]{64}$/D',$row['key']))$keys[]=$row['key'];
            self::flush($keys,$ids,time());
        }catch(\Throwable $e){update_option('rrfr_newsroom_job_error','Samlekøen kunne ikke leses. Kontroller Studio-jobben.',false);}
        finally{delete_option('rrfr_digest_lock');}
    }
    /** Called with the scheduler lock held; persist the reservation before transport. */
    public static function flush(array $keys,array $ids,int $now,?callable $send=null): void {
            $state=get_option(self::OPTION,[]);$decision=self::decision($state,$keys,$now);$state=$decision['state'];
            if(!$decision['send']){update_option(self::OPTION,$state,false);return;}
            $state['status']='sending';$state['attempt_at']=$now;$state['attempt_keys']=$decision['new'];update_option(self::OPTION,$state,false);
            try{
                $message=self::message($decision['count']);
                if(!($send??[MicrosoftMail::class,'send'])($message['subject'],$message['body']))throw new \RuntimeException('Sending ikke bekreftet.');
                $state['seen']=array_values(array_unique(array_merge($state['seen']??[],$decision['new'])));$state['accepted_at']=$now;$state['status']='accepted';unset($state['pending_since'],$state['error']);
                foreach($decision['new'] as $key)if(isset($ids[$key])){$id=$ids[$key];$s=get_post_meta($id,PlayerReview::META,true);if(is_array($s)&&$s){$s['mail']='accepted';$s['mail_at']=gmdate(DATE_ATOM);update_post_meta($id,PlayerReview::META,$s);}}
            }catch(\Throwable $e){$state['status']='uncertain';$state['error']='Kontroller Sendt og Microsoft-tilkoblingen før et nytt utsendingsforsøk.';}
            update_option(self::OPTION,$state,false);
    }
    public static function stop(): void {
        wp_clear_scheduled_hook('rrfr_newsroom_prepare');wp_clear_scheduled_hook('rrfr_newsroom_digest');
    }
    public static function prepare(): void {
        if(!get_option('rrfr_newsroom_enabled',false))return;
        try{self::worker('tick');delete_option('rrfr_newsroom_job_error');}catch(\Throwable $e){update_option('rrfr_newsroom_job_error','Klargjøringen trenger kontroll i nyhetsdesken.',false);}
    }
    public static function register(): void {
        if(!get_option('rrfr_newsroom_enabled',false))return;
        if(!wp_next_scheduled('rrfr_newsroom_prepare'))wp_schedule_event(time()+300,'rrfr_five_minutes','rrfr_newsroom_prepare');
        if(!wp_next_scheduled('rrfr_newsroom_digest'))wp_schedule_event(time()+600,'rrfr_five_minutes','rrfr_newsroom_digest');
    }
}
