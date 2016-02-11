<?php
namespace lemax10\JsonTransformer\Response;

use lemax10\JsonTransformer\Transformer;

abstract class AbstractTransformer {
    protected $response = ['jsonapi' => '1.0'];

    protected $transformer = false;
    protected $castTransformer = false;
    protected $merge = false;

    public function __construct($transformer, $castTransformer = false, $merge = false)
    {
        $this->transformer = is_object($transformer) ? $transformer : new $transformer;
        if ($castTransformer) {
            $this->castTransformer = is_object($castTransformer) ? $castTransformer : new $castTransformer;
        }
        $this->merge = $merge;
        $this->response = collect($this->response);
    }

    protected function setData($data)
    {
        $this->response->put(Transformer::ATTR_DATA, collect([$data]));
        return $this;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function response()
    {
        if(!empty($this->response->get(Transformer::ATTR_INCLUDES)))
            $this->response->put(Transformer::ATTR_INCLUDES, $this->response->get(Transformer::ATTR_INCLUDES)->values());

        return response()->json($this->getResponse());
    }
}