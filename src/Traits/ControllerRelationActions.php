<?php

namespace pelmered\APIHelper\Traits;

use pelmered\APIHelper\APIHelper;
use pelmered\APIHelper\ApiSerializer;

use Illuminate\Support\Facades\Auth;
use App\Http\Requests;
use Illuminate\Support\Facades\Gate;

use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;

trait ControllerRelationActions
{
    protected $resource = null;

    public function getSignleRelation($transformer, $resourceId, $relation, $relationId)
    {
        $fractal = new Manager();

        $model = static::RESOURCE_MODEL;

        $resource = $model::find($resourceId);

        if (!$resource) {
            return $this->notFoundResponse();
        }

        if (Gate::denies('read_'.$relation, $resource)) {
            return $this->permissionDeniedResponse();
        }

        $include = filter_input(INPUT_GET, 'include', FILTER_SANITIZE_STRING);

        if (isset($include)) {
            $fractal->parseIncludes($include);
        }

        $fractal->setSerializer(new ApiSerializer());

        $resourceRelation = $resource->$relation()->find($relationId);

        if (!$resourceRelation) {
            return $this->notFoundResponse();
        }

        $item = new Item($resourceRelation, $transformer);

        $data = $fractal->createData($item)->toArray();

        return $this->response($data);
    }

    /**
     * Get a listing of the resource.
     *
     * @return Response
     */
    public function getRelationList($transformer, $resourceId, $relation)
    {
        $fractal = new Manager();

        $model = static::RESOURCE_MODEL;

        $resource = $model::find($resourceId);

        if (!$resource) {
            return $this->notFoundResponse();
        }

        if (Gate::denies('read_'.$relation, $resource)) {
            return $this->permissionDeniedResponse();
        }

        $include = filter_input(INPUT_GET, 'include', FILTER_SANITIZE_STRING);

        if (isset($include)) {
            $fractal->parseIncludes($include);
        }

        /*
         * TODO: find a solution for excluding parent resource from includes
        if( isset($fractal->includeParams[$relation]) )
        {
            unset($fractal->includeParams[$relation]);
        }
        */

        $fractal->setSerializer(new ApiSerializer());

        $limit = $this->getQueryLimit();

        $resourceRelation = $resource->$relation()->paginate($limit);

        $collection = new Collection($resourceRelation, $transformer);

        $data = $fractal->createData($collection)->toArray();

        return $this->paginatedResponse($resourceRelation, $data);
    }

    public function storeRelationResource($resourceId, $relation, $model = null)
    {
        if (!$model) {
            $model = static::RESOURCE_MODEL;
        }

        $resource = $model::find($resourceId);

        if (!$resource) {
            return $this->notFoundResponse();
        }

        $this->validateAction($resource, 'store_'.$relation);

        $resourceData = $this->createResource($relation, ['post_id' => $resourceId]);

        if ($pos = strrpos($relation, '\\')) {
            $relation = substr($relation, $pos + 1);
        }

        return $this->setStatusCode(200)->createdResponse(
            [
            'meta' => [
                'message' => $relation.' created with ID: ' . $resourceData['id']
            ],
            'data' => $resourceData
            ]
        );
    }

    public function updateRelationResource($resourceId, $relation, $relationId, $model = null)
    {
        $request = app('request');
        $model   = static::RESOURCE_MODEL;

        $resource = $model::find($resourceId);

        if (!$resourceObject = $model::find($resourceId)) {
            return $this->notFoundResponse();
        }

        $this->validateAction($resource, 'update_'.$relation);

        /*
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
        */

        $relationObject = $resourceObject->$relation()->where('id', $relationId)->get();

        $relationObject->fill($request->all());
        $relationObject->save();

        return $this->setStatusCode(200)->response(
            [
            'meta' => [
                'message' => 'Updated '.static::RESOURCE_NAME.' with ID: ' . $relationObject->id
            ],
            'data' => APIHelper::transform($relationObject, $relation)
            //'data' => $resourceObject->toArray()
            ]
        );
    }
}
