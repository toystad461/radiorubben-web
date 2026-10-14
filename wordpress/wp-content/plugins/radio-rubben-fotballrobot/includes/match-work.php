<?php
namespace RadioRubben\Fotballrobot;

/** Durable checkpoints for automated jobs; uncertain paid calls are never repeated. */
final class MatchWork {
    public static function load(string $token): array {
        $s=get_option('rrfr_match_work_'.$token,[]);return is_array($s)?$s:[];
    }
    public static function forMatch(int $id): array {
        $token=get_option('rrfr_match_work_token_'.$id,'');return is_string($token)&&$token!==''?self::load($token):[];
    }
    public static function save(string $token,array $s): void {
        $s['token']=$token;$s['updated_at']=time();
        update_option('rrfr_match_work_'.$token,$s,false);
        if(self::load($token)!==$s)throw new \RuntimeException('Skrivesteget kunne ikke lagres sikkert. Ingen automatisk gjentakelse.');
    }
    public static function begin(int $id,string $token,array $s): void {
        if(!add_option('rrfr_match_work_token_'.$id,$token,'',false))throw new \RuntimeException('Et lagret skrivesteg finnes allerede. Gjenoppta kontrollen.');
        $s['in_flight']=true;self::save($token,$s);
    }
}
