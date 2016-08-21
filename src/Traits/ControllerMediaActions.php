<?php

namespace pelmered\APIHelper\Traits;

use pelmered\APIHelper\ApiSerializer;

use Illuminate\Support\Facades\Auth;
use App\Http\Requests;
use Illuminate\Support\Facades\Gate;

use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;

trait ControllerMediaActions
{
    protected $resource = null;


    private function processMedia($resourceId)
    {
        $request = app('request');
        $data    = $request->all();

        if (isset($data['media'])) {
            // Author should always by current authenticated user
            $author                         = Auth::user();
            $data['media']['resource_id']   = $resourceId;
            $data['media']['resource_type'] = static::RESOURCE_NAME;
            $data['media']['user_id']       = $author->id;

            $resourceData = $this->saveMedia($data['media']);

            return $resourceData;
        }

        return false;
    }

    private function saveMedia($mediaData)
    {
        $media = new \App\Media($mediaData);

        $media->save();

        $media->setBase64($mediaData['file'])->generateImageSizes();

        $mediaData              = $media->toArray();
        $mediaData['file_urls'] = $media->getFileUrl();

        return $mediaData;
    }
}
