<?php
namespace lemax10\JsonTransformer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Pagination\Paginator;
use lemax10\JsonApiTransformer\Response\ObjectPaginationResponse;
use lemax10\JsonTransformer\Request\PaginationRequest;
use lemax10\JsonTransformer\Response\ModelTransformer;
use lemax10\JsonTransformer\Response\PaginateTransformer;
use Cache;

class Transformer {
    const GET_METHOD    = 'GET';
    const POST_METHOD   = 'POST';
    const PUT_METHOD    = 'PUT';
    const DELETE_METHOD = 'DELETE';

    const ATTR_DATA = 'data';
    const ATTR_TYPE = 'type';
    const ATTR_META = 'meta';
    const ATTR_IDENTIFIER = 'id';
    const ATTR_ATTRIBUTES = 'attributes';
    const ATTR_RELATIONSHIP = 'relationships';
    const ATTR_LINKS    = 'links';
    const ATTR_INCLUDES = 'included';

    protected $transformer = false;
    protected $castTransformer = false;
    protected $merge = false;

    protected $cache = false;
    protected $cacheTimeout = 0;
    protected $cacheType = 'remember';

    public function __construct($transformer)
    {
        $this->transformer = is_object($transformer) ? $transformer : new $transformer;
    }

    public function setCast($transformer, $merge = false)
    {
        $this->castTransformer = is_object($transformer) ? $transformer : new $transformer;
        $this->merge = $merge;
        return $this;
    }

    public function toPaginateResponse($query, PaginationRequest $request, $defaultSort = false)
    {
        $transformData = function() use(&$request, &$query, &$defaultSort) {
            if($includes = \Request::input('includes', false) && !($query instanceof Collection))
                $query->with(explode(',', $includes));

            return (new PaginateTransformer($this->transformer, $this->castTransformer, $this->merge, $request, $defaultSort))->runTransformPagination($query);
        };

        if($this->cache === true)
            $transformData = function() use($transformData, &$query, &$request) {
                return $this->{"getCache" . $this->cacheType}($this->generateCacheId($query, $request->all()), $transformData);
            };


        return $transformData()->response();
    }

    public function toModelResponse($model, $cacheMode = true)
    {

        $transformData = function() use(&$model) {
            if($includes = \Request::input('includes', false))
                $model->load(explode(',', $includes));

            return (new ModelTransformer($this->transformer, $this->castTransformer, $this->merge))->runTransformData($model);
        };

        if($this->cache === true)
            $transformData = function() use($transformData, &$model) {
                return $this->{"getCache" . $this->cacheType}($this->generateCacheId($model, \Request::all()), $transformData);
            };

        return $transformData()->response();
    }

    public function setCache($timeout, $type = 'remember')
    {
        $this->cache = true;
        $this->cacheTimeout = $timeout;
        $this->cacheType = $type;

        return $this;
    }

    protected function getCacheRemember($cacheId, $callback)
    {
        return Cache::remember($cacheId, $this->cacheTimeout, $callback);
    }

    protected function generateCacheId($attributes, $request)
    {
        return sha1(json_encode($attributes) . json_encode($request));
    }
}
