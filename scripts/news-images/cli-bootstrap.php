<?php
// Same diagnostic bootstrap used by the existing operational scheduler.
echo "RRNI_CLI_BOOTSTRAP_LOADED\n";
register_shutdown_function(static function(){
    $e=error_get_last();
    if($e&&in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)){
        fwrite(STDERR,'RRNI_CLI_LAST_ERROR '.json_encode(['type'=>$e['type'],'file'=>basename($e['file']),'line'=>$e['line']])."\n");
    }
});
WP_CLI::add_hook('after_wp_load',static function(){echo "RRNI_WORDPRESS_LOADED\n";});
