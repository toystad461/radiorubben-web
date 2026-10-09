"""Build exact replacements, preserving unrelated production edits."""
import json
from pathlib import Path

base = Path(__file__).resolve().parents[1]
old_intro = """        $snapshot=['table'=>[],'scorer'=>[],'fetched'=>time()];
        $info=rr_welcome_match($match);
        if (empty($info['error'])) { $snapshot['table']=rr_welcome_table($info); $snapshot['scorer']=rr_welcome_top_scorer($info); $snapshot['team_id']=(int)($info['ids'][0]??0); }
        set_transient($cache_key,$snapshot,!empty($snapshot['table'])?15*MINUTE_IN_SECONDS:5*MINUTE_IN_SECONDS);"""
new_intro = """        $snapshot=get_option('rr_poll_public_intro_last_'.$id,[]);
        if (!is_array($snapshot)) $snapshot=[];
        if (!wp_next_scheduled('rr_poll_refresh_public_intro',[$id])) {
            wp_schedule_single_event(time()+1,'rr_poll_refresh_public_intro',[$id]);
        }"""
replacements = {
    'inc/bremnes-direkte-test.php': [("require_once __DIR__.'/bremnes-poll-rules.php';", "require_once __DIR__.'/bremnes-poll-rules.php';\nrequire_once __DIR__.'/bremnes-poll-performance.php';")],
    'inc/bremnes-speaker-welcome.php': [(old_intro, new_intro)],
    'inc/bremnes-poll-match.php': [('    rr_poll_rollover_selection();', '    rr_poll_schedule_rollover();')],
    'inc/match-rollover.php': [('    rr_poll_rollover_selection();', '    rr_poll_schedule_rollover();')],
    'inc/bremnes-poll-test.php': [('refresh();},5000);setInterval(render,500);', "refresh();},<?php echo $rr_control ? 5000 : 10000; ?>);setInterval(render,500);")],
}
plugin = base / 'theme-next/rr-site-functions'
for name, edits in replacements.items():
    source = (plugin / name).read_text(encoding='utf-8')
    for before, after in edits:
        assert source.count(after) == 1, name
        assert source.replace(after, before).count(before) == 1, name
payload = {'replacements': replacements, 'new_file': 'inc/bremnes-poll-performance.php',
           'new_content': (plugin / 'inc/bremnes-poll-performance.php').read_text(encoding='utf-8')}
print(json.dumps(payload))
