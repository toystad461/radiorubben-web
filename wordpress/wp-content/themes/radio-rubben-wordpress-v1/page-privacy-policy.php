<?php
// Keep old links working while the canonical privacy page lives at /personvern/.
wp_safe_redirect(home_url('/personvern/'), 301);
exit;
