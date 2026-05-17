<?php

use Lalalili\VideoUpload\Tests\TestCase;
use Lalalili\VideoUpload\Tests\UploadCenterTestCase;

uses(TestCase::class)->in(__DIR__.'/Unit', __DIR__.'/Models');
uses(TestCase::class)->in(__DIR__.'/Feature/VideoUploadServiceTest.php');
uses(UploadCenterTestCase::class)->in(__DIR__.'/Feature/UploadCenter');
