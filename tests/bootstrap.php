<?php

$loader = require __DIR__.'/../../../vendor/autoload.php';

$loader->addPsr4('Lalalili\\CourseCore\\', __DIR__.'/../../course-core/src/');
$loader->addPsr4('Lalalili\\VideoUpload\\', __DIR__.'/../src/');
$loader->addPsr4('Lalalili\\VideoUpload\\Tests\\', __DIR__.'/');

require __DIR__.'/Pest.php';

return $loader;
