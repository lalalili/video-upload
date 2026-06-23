<?php

namespace Lalalili\VideoUpload\Tests\Fixtures;

use Lalalili\VideoUpload\Models\VideoUploadSession;

class HostVideoUploadSession extends VideoUploadSession
{
    public function casts(): array
    {
        return array_merge(parent::casts(), [
            'status' => HostVideoUploadSessionStatus::class,
        ]);
    }
}
