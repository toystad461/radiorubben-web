<?php
define('ABSPATH',__DIR__);
set_error_handler(static function($n,$s) { throw new ErrorException($s,0,$n); });
class WP_Error { function __construct(public $code,public $message) {} function get_error_message(){return $this->message;} }
function is_wp_error($v){return $v instanceof WP_Error;}
function sanitize_text_field($v){return trim(strip_tags($v));}
function get_option($key,$default=''){return ['rr_fd_cid'=>'123','rr_fd_cwd'=>'11111111-1111-1111-1111-111111111111'][$key]??$default;}
function get_transient($key){return $GLOBALS['cache'][$key]??false;}
function set_transient($key,$value,$ttl){$GLOBALS['cache'][$key]=$value;}
function wp_safe_remote_get($url,$args){$GLOBALS['requests'][]=[$url,$args];if(!empty($GLOBALS['throw_transport']))throw new RuntimeException($url);return $GLOBALS['response'];}
function wp_remote_retrieve_response_code($r){return $r['status'];}
function wp_remote_retrieve_body($r){return $r['body'];}
function wp_date($format,$now,$tz){return (new DateTimeImmutable('2026-10-10',$tz))->format($format);}
require __DIR__.'/../rr-site-functions/inc/bremnes-poll-fotballdata.php';
$checks=0;
function verify($ok,$message){if(!$ok)throw new RuntimeException($message);$GLOBALS['checks']++;}
$player=['PersonId'=>91,'PlayerId'=>51,'FirstName'=>'Test','SurName'=>'Spiller','PersonInfoHidden'=>false,'PlayerShirtNumber'=>1,'PositionId'=>1022,'Position'=>'Keeper','SortOrder'=>1];
$raw=['MatchId'=>8985501,'HomeTeamId'=>30365,'AwayTeamId'=>30399,'HomeTeamClubId'=>827,'AwayTeamClubId'=>1327,'HomeTeamName'=>'Bremnes','AwayTeamName'=>'Motstander','MatchStartDate'=>'/Date(1791574200000-0000)/','StadiumName'=>'ScaleAQ Stadion','TournamentName'=>'5. divisjon','HomeTeamPlayers'=>[$player],'AwayTeamPlayers'=>[$player+['extra'=>true]],'Referees'=>[['Email'=>'private@example.test','MobilePhone'=>'12345678']]];
$reserve=$player;$reserve['PersonId']=92;$reserve['PlayerShirtNumber']=12;$reserve['Position']='Reserve (keeper)';$raw['HomeTeamPlayers'][]=$reserve;
$match=rr_poll_fd_parse_match($raw,8985501);
verify(!is_wp_error($match)&&$match['starters']===[1]&&$match['bench']===[12],'Confirmed player roles');
verify($match['home_id']===30365&&$match['away_id']===30399&&$match['person_ids'][1]===91,'Identity retained');
verify($match['kickoff']==='2026-10-09T19:30:00+02:00','Oslo date from provider epoch');
verify(rr_poll_fd_date('/Date('.(strtotime('2026-12-10T19:30:00Z')*1000).'-0000)/')->format(DATE_ATOM)==='2026-12-10T19:30:00+01:00','Provider wall time also works in winter');
verify(rr_poll_fd_date('/Date(1791574200000)/')->format(DATE_ATOM)==='2026-10-09T21:30:00+02:00','Unmarked JSON epoch stays UTC');
verify(!str_contains(json_encode($match),'private@example.test')&&!isset($match['Referees']),'Only allowlisted normalized fields');
$bad=$raw;$bad['MatchId']=5;verify(is_wp_error(rr_poll_fd_parse_match($bad,8985501)),'Wrong match rejected');
$bad=$raw;$bad['HomeTeamId']=5;verify(is_wp_error(rr_poll_fd_parse_match($bad,8985501)),'Wrong home team rejected');
$bad=$raw;$bad['HomeTeamPlayers'][]=$player;verify(is_wp_error(rr_poll_fd_parse_match($bad,8985501)),'Duplicate shirt rejected');
$bad=$raw;$bad['HomeTeamPlayers'][0]['PersonInfoHidden']=true;verify(is_wp_error(rr_poll_fd_parse_match($bad,8985501)),'Hidden person rejected');
$bad=$raw;$bad['HomeTeamPlayers'][0]['Position']='Unknown';verify(is_wp_error(rr_poll_fd_parse_match($bad,8985501)),'Unknown role rejected');
$bad=$raw;$bad['HomeTeamPlayers'][0]['PlayerShirtNumber']=0;verify(is_wp_error(rr_poll_fd_parse_match($bad,8985501)),'Missing shirt number rejected');
$bad=$raw;$bad['HomeTeamPlayers'][0]['FirstName']='<script></script>';verify(is_wp_error(rr_poll_fd_parse_match($bad,8985501)),'Empty sanitized name rejected');
$bad=$raw;unset($bad['AwayTeamPlayers']);verify(is_wp_error(rr_poll_fd_parse_match($bad,8985501)),'Missing roster is not an empty confirmed roster');
$empty=$raw;$empty['HomeTeamPlayers']=[];verify(rr_poll_fd_parse_match($empty,8985501)['starters']===[],'Pending lineup cannot open voting');
$fixture=$raw;unset($fixture['HomeTeamPlayers'],$fixture['AwayTeamPlayers'],$fixture['Referees']);$fixture+=['Cancelled'=>false,'Postponed'=>false,'Interrupted'=>false];
$future=$fixture;$future['MatchId']=8985510;$future['MatchStartDate']='/Date(1792179000000-0000)/';
$list=['TeamId'=>30365,'Matches'=>[$future,$fixture]];
verify(rr_poll_fd_pick_home($list,30365,'2026-10-09')===8985501,'Today preferred to future');
verify(rr_poll_fd_pick_home($list,30365,'2026-10-10')===8985510,'Past matches skipped');
$list['Matches'][0]['Cancelled']=true;verify(is_wp_error(rr_poll_fd_pick_home($list,30365,'2026-10-10')),'Cancelled match skipped');
verify(is_wp_error(rr_poll_fd_pick_home($list,48835,'2026-10-09')),'Wrong fixture team rejected');
$GLOBALS['response']=['status'=>200,'body'=>json_encode($raw)];
$first=rr_poll_fd_fetch_match(8985501);$second=rr_poll_fd_fetch_match(8985501);
verify($first===$second&&count($GLOBALS['requests'])===1,'Repeated import uses normalized short cache');
verify(!str_contains(json_encode($GLOBALS['cache']),'private@example.test'),'Raw contacts never cached');
[$url,$args]=$GLOBALS['requests'][0];verify(str_starts_with($url,'https://api.fotballdata.no/v1/matches/8985501/people?')&&str_contains($url,'clubid=827')&&$args['redirection']===0,'Documented provider route, club binding and no redirects');
verify(is_wp_error(rr_poll_fd_request('https://example.test/'))&&count($GLOBALS['requests'])===1,'Arbitrary API path rejected before request');
$GLOBALS['response']=['status'=>403,'body'=>'secret'];verify(is_wp_error(rr_poll_fd_fetch_match(8985501,false)),'Provider failure returns error');
$GLOBALS['throw_transport']=true;$error=rr_poll_fd_fetch_match(8985501,false);verify(is_wp_error($error)&&!str_contains($error->get_error_message(),'cwd='),'Transport exceptions cannot leak credential URLs');
echo 'PASS: '.$checks." Fotballdata checks\n";
