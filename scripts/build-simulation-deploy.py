"""Create a narrowly scoped rehearsal deployment, preserving production drift."""
import json
from pathlib import Path
base = Path(__file__).resolve().parents[1]
plugin = base / 'theme-next/rr-site-functions'
before = "'dashboard-prototype'"
after = "'dashboard-prototype', 'poll-simulation'"
heading = '<h2>Velg lag og kamp</h2>'
link = '<p><a href="<?php echo esc_url(home_url(\'/avstemningstest/\')); ?>">Hent en tidligere kamp og start simulert avstemning</a></p>'
replacements = {
    'inc/bremnes-poll-match.php': [[heading, heading + '\n' + link]],
    'rr-site-functions.php': [[before, after]],
}
for name, edits in replacements.items():
    source = (plugin / name).read_text(encoding='utf-8')
    for old, new in edits:
        assert source.count(new) == 1, name
print(json.dumps({'replacements': replacements, 'new_file': 'inc/poll-simulation.php',
                 'new_content': (plugin / 'inc/poll-simulation.php').read_text(encoding='utf-8')}))
