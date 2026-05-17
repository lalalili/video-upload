<?php

namespace Lalalili\VideoUpload\Tests;

abstract class UploadCenterTestCase extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        config()->set('video-upload.upload_center.enabled', true);
        config()->set('video-upload.upload_center.middleware', []);
        config()->set('video-upload.upload_center.prefix', 'upload-center');
        config()->set('video-upload.upload_center.route_name', 'video-upload.upload-center');
    }
}
