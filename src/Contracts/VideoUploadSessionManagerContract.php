<?php

namespace Lalalili\VideoUpload\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

interface VideoUploadSessionManagerContract
{
    /**
     * Create an upload session from a request.
     *
     * @return array{upload_session: Model}
     */
    public function createUploadSession(Request $request): array;

    public function completeUploadSession(Model $session): void;
}
