<?php

namespace pelmered\RestTraits\Controllers;

use pelmered\RestTraits\ApiSerializer;

use Illuminate\Support\Facades\Auth;
use App\Http\Requests;
use Illuminate\Support\Facades\Gate;

use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;

trait ApiControllerActionsTrait
{
    protected $resource = null;

    /*
    public function index()
    {
        $m = self::MODEL;
        return $this->listResponse($m::all());
    }
    */

    public function getCreatedResourceObject( )
    {
        return $this->resource;
    }


    /**
     * Get a listing of the resource.
     *
     * @return Response
     */
    public function getList($transformer)
    {
        $fractal = new Manager();

        $m = static::RESOURCE_MODEL;

        if (Gate::denies('read', $m ) ) {
            return $this->permissionDeniedResponse();
        }

        if (isset($_GET['include'])) {
            $fractal->parseIncludes($_GET['include']);
        }

        $fractal->setSerializer(new ApiSerializer());

        $limit = $this->getQueryLimit();

        $Resources = $m::orderBy('created_at', 'desc')->paginate($limit);

        $collection = new Collection($Resources, $transformer);

        $data = $fractal->createData($collection)->toArray();

        return $this->paginatedResponse($Resources,$data);
    }




    /**
     * Get the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function getSingle($Transformer, $resourceId)
    {
        $fractal = new Manager();

        $m = static::RESOURCE_MODEL;

        if (Gate::denies('read', $m ) ) {
            return $this->permissionDeniedResponse();
        }

        $resource = $m::find($resourceId);

        if(!$resource) {
            return $this->notFoundResponse();
        }

        $fractal->setSerializer(new ApiSerializer());

        if (isset($_GET['include'])) {
            $fractal->parseIncludes($_GET['include']);
        }
        $item = new Item($resource, $Transformer);

        $data = $fractal->createData($item)->toArray();

        return $this->response($data);
    }

    public function storeResource( $m = null )
    {
        if(!$m)
        {
            $m = static::RESOURCE_MODEL;
        }

        $this->validateAction($m, 'store');

        $resourceData = $this->createResource( $m );

        return $this->setStatusCode(200)->createdResponse([
            'meta' => [
                'message' => static::RESOURCE_NAME.' created with ID: ' . $resourceData['id']
            ],
            'data' => \App\transform($this->getCreatedResourceObject(), static::RESOURCE_NAME )
        ]);
    }

    protected function createResource( $m, $merge = [] )
    {
        $request = app('request');
        $data = $request->all();

        // Author should always by current authenticated user
        $author = Auth::user();
        if( $author )
        {
            $data['user_id'] = $author->id;
        }

        $data = $data + $merge;

        $resourceObject = $m::create($data);

        $this->resource = $resourceObject;

        $resourceData = $resourceObject->toArray();

        if(isset($data['media']) && method_exists($this, 'processMedia'))
        {
            $media = $this->processMedia(isset($merge['post_id']) ? $merge['post_id'] : $resourceObject->id, $m);

            if($media)
            {
                $resourceData['media'] = $media;
            }
        }

        return $resourceData;
    }

    private function processMedia($resource_id)
    {
        $request = app('request');
        $data = $request->all();

        if( isset($data['media']) )
        {
            // Author should always by current authenticated user
            $author = Auth::user();
            $data['media']['resource_id']   = $resource_id;
            $data['media']['resource_type'] = static::RESOURCE_NAME;
            $data['media']['user_id']        = $author->id;

            $resourceData = $this->saveMedia($data['media']);

            return $resourceData;
        }

        return false;
    }
    private function saveMedia($mediaData)
    {
        $media = new \pelmered\RestTraits\Models\Media($mediaData);

        $media->save();

        $media->setBase64($mediaData['file'])->generateImageSizes();

        $mediaData = $media->toArray();
        $mediaData['file_urls'] = $media->getFileUrl();

        return $mediaData;
    }

    public function updateResource($id)
    {
        $request = app('request');
        $m = static::RESOURCE_MODEL;

        if(!$resourceObject = $m::find($id))
        {
            return $this->notFoundResponse();
        }

        if (Gate::denies('update', $resourceObject)) {
            return $this->permissionDeniedResponse();
        }

        try
        {
            $v = \Validator::make($request->all(), $this->getValidationRules('update'));
            if($v->fails())
            {
                throw new \Exception("ValidationException");
            }
        }catch(\Exception $ex)
        {
            $resourceObject = ['form_validations' => $v->errors(), 'exception' => $ex->getMessage()];
            return $this->validationErrorResponse('Validation error', $resourceObject);
        }

        $resourceObject->fill($request->all());
        $resourceObject->save();

        return $this->setStatusCode(200)->response([
            'meta' => [
                'message' => 'Updated '.static::RESOURCE_NAME.' with ID: ' . $resourceObject->id
            ],
            'data' => \App\transform($resourceObject, static::RESOURCE_NAME )
            //'data' => $resourceObject->toArray()
        ]);
    }

    public function destroyResource($id)
    {
        $m = static::RESOURCE_MODEL;
        if(!$resourceObject = $m::find($id))
        {
            return $this->notFoundResponse();
        }

        if (Gate::denies('delete', $resourceObject)) {
            return $this->permissionDeniedResponse();
        }

        $resourceObject->delete();

        return $this->setStatusCode(200)->response([
            'meta' => [
                'message' => 'Deleted '.static::RESOURCE_NAME.' with ID: ' . $resourceObject->id
            ],
            'data' => $resourceObject->toArray()
        ]);
    }

    public function validateAction( $model, $action )
    {
        $request = app('request');

        if( is_string($model))
        {
            $model = new $model();
        }

        if (Gate::denies($action, $model ) ) {
            return $this->permissionDeniedResponse();
        }

        try
        {
            $v = \Validator::make($request->all(), $this->getValidationRules($action));
            if($v->fails())
            {
                throw new \Exception("ValidationException");
            }

        }catch(\Exception $ex)
        {
            $resourceObject = ['form_validations' => $v->errors(), 'exception' => $ex->getMessage()];
            return $this->validationErrorResponse('Validation error', $resourceObject);
        }
    }

    function getValidationRules( $type )
    {

        if( isset( $this->validationRules[$type] ) )
        {
            return $this->validationRules[$type];
        }

        return [];
    }

}
