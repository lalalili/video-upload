<?php

namespace Lalalili\VideoUpload\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Lalalili\VideoUpload\Contracts\VideoUploadSessionManagerContract;

class NullVideoUploadSessionManager implements VideoUploadSessionManagerContract
{
    public function createUploadSession(Request $request): array
    {
        abort(501, 'Bind '.VideoUploadSessionManagerContract::class.' in your service provider to enable upload session creation.');
    }

    public function completeUploadSession(Model $session): void
    {
        abort(501, 'Bind '.VideoUploadSessionManagerContract::class.' in your service provider to enable upload session completion.');
    }
}
