<?php

namespace App\Observers;

use App\Models\History;
use Carbon\Carbon;

class FilesObserver
{
    public function creating($model)
    {
        $attributes = $model->getAttributes();

        $splFileInfo = $splFileInfo = new \SplFileInfo($attributes['path']);

        $model->basename = $splFileInfo->getBasename();
        $model->ext = $splFileInfo->getExtension();
        $model->uri = str_replace(storage_path(), '/files', $splFileInfo->getRealPath());
        $model->size = filesize($splFileInfo->getRealPath());
        $model->mime = mime_content_type($splFileInfo->getRealPath());

        if (explode('/', $model->mime)[0] = 'image') {
            list($model->width, $model->height) = getimagesize($model->path);
        }
    }

}
