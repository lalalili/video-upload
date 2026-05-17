<?php

namespace Lalalili\VideoUpload\Enums;

enum VideoUploadSessionStatus: string
{
    case Created = 'created';
    case Uploading = 'uploading';
    case Uploaded = 'uploaded';
    case Importing = 'importing';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function canCancel(): bool
    {
        return in_array($this, [
            self::Created,
            self::Uploading,
            self::Uploaded,
            self::Importing,
            self::Processing,
        ], true);
    }

    public function canRetry(): bool
    {
        return in_array($this, [
            self::Failed,
            self::Cancelled,
        ], true);
    }

    public function canUpdateProgress(): bool
    {
        return in_array($this, [
            self::Created,
            self::Uploading,
        ], true);
    }
}
