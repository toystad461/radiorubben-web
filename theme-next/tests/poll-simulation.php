<?php
define('ABSPATH',__DIR__);
class WP_Error { function __construct(public $code,public $message) {} }
function is_wp_error($v){return $v instanceof WP_Error;}
function add_action(...$args){}
function wp_generate_uuid4(){static $n=0; return 'test-revision-'.++$n;}
require __DIR__.'/../rr-site-functions/inc/bremnes-poll-rules.php';
require __DIR__.'/../rr-site-functions/inc/poll-simulation.php';
function verify($condition,$message){if(!$condition)throw new RuntimeException($message);}
$match=['id'=>8985501,'roster'=>[1=>'Test Starter',12=>'Test Reserve'],'starters'=>[1],'bench'=>[12]];
$sim=rr_poll_sim_new($match);
verify(!$sim['state']['finished']&&!$sim['state']['opened'],'Historical match begins with a fresh test');
verify(is_wp_error(rr_poll_sim_action($sim,'vote',1,1000)),'Cannot vote before start');
$sim=rr_poll_sim_action($sim,'start',0,1000);
verify($sim['state']['opened'],'Historical match can start');
verify(is_wp_error(rr_poll_sim_action($sim,'vote',12,1001)),'Unused reserve cannot receive votes');
$sim=rr_poll_sim_action($sim,'substitute',12,1001);
$sim=rr_poll_sim_action($sim,'vote',12,1002);
verify($sim['votes'][12]===1,'Substitute becomes eligible');
verify(is_wp_error(rr_poll_sim_action($sim,'vote',999,1002)),'Unknown player rejected');
$sim=rr_poll_sim_action($sim,'half',0,3700);
$sim=rr_poll_sim_action($sim,'second',0,3800);
$fast=rr_poll_sim_action($sim,'minute85',0,3801);
verify(rr_poll_is_closed($fast['state'],3801)&&is_wp_error(rr_poll_sim_action($fast,'vote',1,3802)),'Fast-forward test closes voting at 85 minutes');
verify(is_wp_error(rr_poll_sim_action($sim,'vote',1,6200)),'85-minute rule applies');
$sim=rr_poll_sim_action($sim,'finish',0,6500);
verify($sim['state']['finished']&&$sim['state']['closed'],'Finishing closes only the test');
verify(is_wp_error(rr_poll_sim_action($sim,'start',0,6501)),'Finished test cannot restart implicitly');
$reset=rr_poll_sim_action($sim,'reset');
verify($reset['votes']===[]&&!$reset['state']['finished']&&$reset['revision']!==$sim['revision'],'Explicit reset clears test votes and changes revision');
$empty=rr_poll_sim_new($match+[]);$empty['match']['starters']=[];
verify(is_wp_error(rr_poll_sim_action($empty,'start')),'Missing lineup cannot start');
// Pure state transitions cannot accidentally invoke WordPress persistence or archive functions:
// no option-writing stubs are supplied, so any such side effect fails this test.
echo "PASS: historical simulation lifecycle, eligibility, closure and reset\n";
