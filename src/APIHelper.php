<?php

namespace pelmered\APIHelper;

class APIHelper
{

    public static function getExceptionMessage($exception )
    {
        $data = json_decode($exception->getMessage());

        if( !$data )
        {
            return false;
        }
        return $data;
    }

    public static function transform($resource, $resourceType)
    {
        $transformerPath = '\App\Transformers\\'.$resourceType.'Transformer';
        $transformer = new $transformerPath();

        if(!isset($_GET['include']))
        {
            return $transformer->transform($resource);
        }

        $includes = explode(',', $_GET['include']);

        $extraData = [];

        if(is_array($includes) && !empty($includes))
        {

            foreach($includes AS $include)
            {
                $methodName = 'include'.ucfirst($include);

                if(method_exists($transformer, $methodName) )
                {
                    $includeObject = $transformer->$methodName($resource);

                    if(is_a($includeObject, 'League\Fractal\Resource\item'))
                    {
                        $extraData[$include] = $includeObject->getData();
                    }
                    else
                    {
                        //$extraData[$include] = $includeObject->toArray();
                    }

                    //call_user_func([$transformer, $methodName], $resource);
                }

            }
        }

        return array_merge($transformer->transform($resource), $extraData);
    }

    public static function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

}



