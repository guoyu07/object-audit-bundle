<?php

use Doctrine\Common\Annotations\AnnotationRegistry;

$loader = require __DIR__.'/../../vendor/autoload.php';

require __DIR__.'/AppKernel.php';

AnnotationRegistry::registerLoader([$loader, 'loadClass']);

return $loader;