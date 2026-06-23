<?php

namespace Lalalili\VideoUpload\Tests\Fixtures;

enum HostVideoUploadSessionStatus: string
{
    case Created = 'created';
    case Uploading = 'uploading';
    case Uploaded = 'uploaded';
    case Importing = 'importing';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
