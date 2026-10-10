<?php
// Accept both public states of the match page; never accept an empty/error page.
function rr_deploy_poll_page_valid($html) {
    return str_contains($html,'id="poll-match-heading"') && str_contains($html,'id="poll-score"')
        && (str_contains($html,'!busy&&!document.hidden')
            || str_contains($html,'Kampen er arkivert. Resultat og hendelser er bevart'));
}
if (PHP_SAPI!=='cli') exit(1);
if (($argv[1]??'')==='--self-test') {
    $match='<h2 id="poll-match-heading">Bremnes</h2><span id="poll-score">0–0</span>';
    foreach ([[$match.'!busy&&!document.hidden',true],[$match.'Kampen er arkivert. Resultat og hendelser er bevart',true],['',false],[$match,false],['Kampen er arkivert. Resultat og hendelser er bevart',false]] as [$html,$expected]) {
        if (rr_deploy_poll_page_valid($html)!==$expected) throw new RuntimeException('Public match state check failed');
    }
    echo "PASS: live and archived pages accepted; empty, incomplete and unrelated pages rejected\n";
    exit(0);
}
$html=is_file($argv[1]??'')?file_get_contents($argv[1]):'';
if (!rr_deploy_poll_page_valid($html)) { fwrite(STDERR,"Public match page did not contain a valid live or archived view\n"); exit(1); }
echo "VERIFIED: public match view\n";

