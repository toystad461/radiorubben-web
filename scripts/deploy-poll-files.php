<?php
// CLI-only, exact replacements in a closed set of production files.
if (PHP_SAPI!=='cli') exit(1);
$mode=$argv[1]??''; $stage=realpath($argv[2]??'');
$root='/run/webroots/r1417157/wp-content/plugins/rr-site-functions';
if (!$stage || !in_array($mode,['plan','apply','rollback'],true) || is_link($root) || !is_dir($root)) exit(1);
$root=realpath($root);
if ($root===false) throw new RuntimeException('Plugin root cannot be resolved');
$allowed=['inc/bremnes-poll-performance.php','inc/bremnes-direkte-test.php','inc/bremnes-speaker-welcome.php','inc/bremnes-poll-match.php','inc/match-rollover.php','inc/bremnes-poll-test.php'];
function digest($path) { return is_file($path)?hash_file('sha256',$path):null; }
function write_atomic($path,$bytes) {
    $temp=tempnam(dirname($path),'.rr-poll-');
    try {
        if (file_put_contents($temp,$bytes)!==strlen($bytes) || !chmod($temp,0644) || !rename($temp,$path)) throw new RuntimeException('Atomic install failed');
    } finally { if (is_file($temp)) unlink($temp); }
}
if ($mode==='plan') {
    $payload=json_decode(file_get_contents($stage.'/payload.json'),true,512,JSON_THROW_ON_ERROR);
    if ($payload['new_file']!==$allowed[0] || array_keys($payload['replacements'])!==array_slice($allowed,1)) throw new RuntimeException('Unexpected file set');
    mkdir($stage.'/backup',0700); mkdir($stage.'/candidate',0700);
    $receipt=[];
    foreach ($allowed as $name) {
        $path=$root.'/'.$name;
        if (is_link($path) || realpath(dirname($path))!==$root.'/inc') throw new RuntimeException('Invalid source path');
        $old=is_file($path)?file_get_contents($path):null;
        if ($name===$payload['new_file']) {
            $next=$payload['new_content'];
            if ($old!==null && $old!==$next) throw new RuntimeException('New module already exists with different code');
        } else {
            if ($old===null) throw new RuntimeException('Source missing: '.$name);
            $next=$old;
            foreach ($payload['replacements'][$name] as [$before,$after]) {
                if (substr_count($next,$after)===1) continue;
                if (substr_count($next,$before)!==1) throw new RuntimeException('Exact patch mismatch: '.$name);
                $next=str_replace($before,$after,$next);
            }
        }
        $key=basename($name);
        if ($old!==null) file_put_contents($stage.'/backup/'.$key,$old);
        file_put_contents($stage.'/candidate/'.$key,$next);
        exec('php -l '.escapeshellarg($stage.'/candidate/'.$key).' 2>&1',$lint,$code);
        if ($code!==0) throw new RuntimeException('PHP lint failed: '.$name);
        $receipt[$name]=['before'=>$old===null?null:hash('sha256',$old),'after'=>hash('sha256',$next)];
    }
    file_put_contents($stage.'/receipt.json',json_encode($receipt,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo "PLAN: all exact replacements and PHP syntax passed\n";
} else {
    $receipt=json_decode(file_get_contents($stage.'/receipt.json'),true,512,JSON_THROW_ON_ERROR);
    if (array_keys($receipt)!==$allowed) throw new RuntimeException('Invalid receipt');
    if ($mode==='apply') {
        foreach ($receipt as $name=>$hashes) if (digest($root.'/'.$name)!==$hashes['before']) throw new RuntimeException('Concurrent edit detected: '.$name);
        foreach ($receipt as $name=>$hashes) write_atomic($root.'/'.$name,file_get_contents($stage.'/candidate/'.basename($name)));
        foreach ($receipt as $name=>$hashes) if (digest($root.'/'.$name)!==$hashes['after']) throw new RuntimeException('Post-install hash mismatch');
        echo "APPLY: installed and hash verified\n";
    } else {
        foreach (array_reverse($receipt,true) as $name=>$hashes) {
            $path=$root.'/'.$name; $current=digest($path);
            if ($current===$hashes['before']) continue;
            if ($current!==$hashes['after']) throw new RuntimeException('Concurrent edit blocks rollback: '.$name);
            if ($hashes['before']===null) { if (!unlink($path)) throw new RuntimeException('Rollback removal failed'); }
            else write_atomic($path,file_get_contents($stage.'/backup/'.basename($name)));
        }
        echo "ROLLBACK: restored original files\n";
    }
}
