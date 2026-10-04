<?php
$file = $argv[1];
$expected = '1d20a26992358dde6a7f1562c2e5f29e5e437ea016c84e46923a488253a51b7f';
if (hash_file('sha256',$file)!==$expected) throw new RuntimeException('Homepage changed; refusing replacement.');
$source = file_get_contents($file);
$anchor = "\n<style>\n.rr-home-latest";
if (substr_count($source,$anchor)!==1) throw new RuntimeException('Homepage anchor ambiguous.');
$new = str_replace($anchor,"\n<?php do_action('rrpw_homepage'); ?>\n".$anchor,$source);
if (file_put_contents($file.'.candidate',$new)!==strlen($new)) throw new RuntimeException('Could not write candidate.');
