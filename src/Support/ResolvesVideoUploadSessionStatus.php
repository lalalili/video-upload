<?php

namespace Lalalili\VideoUpload\Support;

use BackedEnum;
use Lalalili\VideoUpload\Enums\VideoUploadSessionStatus;
use Lalalili\VideoUpload\Models\VideoUploadSession;

trait ResolvesVideoUploadSessionStatus
{
    protected function status(VideoUploadSession $session): VideoUploadSessionStatus
    {
        $status = $session->getAttribute('status');

        if ($status instanceof VideoUploadSessionStatus) {
            return $status;
        }

        if ($status instanceof BackedEnum) {
            return VideoUploadSessionStatus::from((string) $status->value);
        }

        return VideoUploadSessionStatus::from((string) $status);
    }
}
