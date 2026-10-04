<?php
use RadioRubben\Fotballrobot\ReviewDesk;
try {
 $model=ReviewDesk::model(1091);ob_start();ReviewDesk::article(1091,$model);$html=ob_get_clean();
 if(!str_contains($html,'name="editorial_facts"') || !str_contains($html,'Egne opplysninger til saken')) throw new RuntimeException('Redaksjonsfeltet mangler.');
 if((new ReflectionMethod('RadioRubben\\Fotballrobot\\PlayerReview','decide'))->getNumberOfParameters()!==6)throw new RuntimeException('Ny skriveflyt mangler.');
 echo "EDITOR_FACTS_RELEASE_OK\n";
} catch(Throwable $e){WP_CLI::error($e->getMessage());}
