<?php
/** Parse every PHP file without running application code. */
$directory = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/..' ) );
$count = 0;
foreach ( $directory as $file ) {
    if ( 'php' !== $file->getExtension() ) { continue; }
    token_get_all( file_get_contents( $file->getPathname() ), TOKEN_PARSE );
    $count++;
}
echo "$count PHP files parsed successfully\n";
