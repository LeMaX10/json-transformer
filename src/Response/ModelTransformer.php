<?php
namespace lemax10\JsonTransformer\Response;

use \Illuminate\Support\Collection;
use Illuminate\Support\Str;
use lemax10\JsonTransformer\Relations\PivotApi;
use lemax10\JsonTransformer\Transformer;
use Request;

class ModelTransformer extends AbstractTransformer
{
    protected $instance = [];
    public function newInstance($transformer, $castTransformer = false, $merge = false)
    {
        $instanceId = (is_object($transformer) ? get_class($transformer) : $transformer) . $castTransformer . $merge;
        if(!isset($instance[$instanceId]))
            $instance[$instanceId] = new ModelTransformer($transformer, $castTransformer, $merge);

        return $instance[$instanceId];
    }

    public function runTransformData($model)
    {
        $transform = $this->checkTransform($model);
        $this->setData($transform);

        return $this;
    }
    
    protected function checkTransform($model)
    {
        if ($model instanceof \Illuminate\Database\Eloquent\Collection) {
            return $this->collectionTransform($model);
        }

        return $this->modelTransform($model);
    }
    
    protected function collectionTransform($modelCollection) : Collection
    {
        return $modelCollection->transform(function ($model) {
            return $this->modelTransform($model);
        });
    }

    protected function modelTransform($model) : Collection
    {
        if (method_exists($model, 'transformModel')) 
            $model = $model->transformModel();
        
        $transformCollectedModel = collect($model);
        $transformModel = new Collection([
            Transformer::ATTR_TYPE => $this->getType($model),
            Transformer::ATTR_IDENTIFIER => $this->getIdentifier($model),
            Transformer::ATTR_ATTRIBUTES => $this->getAttributes($transformCollectedModel)
        ]);

        $parseLinks = $this->parseLinks($transformCollectedModel);
        if($parseLinks->count())
            $transformModel->put(Transformer::ATTR_LINKS, $parseLinks);

        if (count($model->getRelations())) {
            $relationShips = $this->parseRelations($model, $this->transformer);

            if (isset($relationShips['relations']) && $relationShips['relations']->count())
                $transformModel->put(Transformer::ATTR_RELATIONSHIP, $relationShips['relations']);

            if (isset($relationShips['cast']))
                $transformModel = $this->mergeModels($transformModel, $relationShips['cast']);
        }

        $this->parseMeta($model);

        return $transformModel;
    }

    protected function getType($model) : string
    {
        if ($this->castTransformer !== false) {
            return $this->castTransformer->getAlias();
        }

        return $this->transformer->getAlias();
    }

    protected function getIdentifier($model) : int
    {
        return (int) $model->id;
    }

    protected function getAttributes($transformModel) : Collection
    {
        if (count($this->transformer->getAliasedProperties())) {
            foreach ($this->transformer->getAliasedProperties() as $modelAttribute => $alias) {
                if (empty($transformModel->get($modelAttribute))) continue;

                $transformModel->put($alias, $transformModel->get($modelAttribute))->pull($modelAttribute);
            }
        }

        if (count($this->transformer->getHideProperties())) {
            $transformModel = $transformModel->except($this->transformer->getHideProperties());
        }

        if ($filterAttribute = Request::input('filter.' . $this->transformer->getAlias(), false)) {
            $transformModel = $transformModel->only(explode(',', $filterAttribute));
        }

        return $this->shakeAttributes($transformModel);
    }

    protected function shakeAttributes($transformModel) : Collection
    {
        foreach ($transformModel->toArray() as $attribute => $value) {
            if (Str::contains($attribute, "_") === false || Str::contains($attribute, "_id") !== false) continue;

            $transformModel->put(Str::camel($attribute), $value)->pull($attribute);
        }

        return $transformModel;
    }

    protected function parseRelations($model, $transformer) : Collection
    {
        $relations = collect([
            'relations' => collect([])
        ]);

        $relationsTransformer = $transformer->getRelationships();
        foreach ($model->getRelations() as $key => $relation) {
            //TODO:  защита от дибила (Временно)
            if($relation instanceof Collection) continue;

            if (!in_array($key, array_keys($relationsTransformer)) || (empty($relation) || !count($relation))) {
                $model->setRelation($key, []);
                continue;
            }

            if ($relation instanceof PivotApi)
            {
                $this->setRelation($key, []);
                continue;
            }

            if(!$transformer)
                $transformer = $relation::getTransformer();

            $transformer = isset($relationsTransformer[$key]) ? $relationsTransformer[$key]['transformer'] : $transformer;

            if(!is_object($transformer))
                $transformer = new $transformer();

            if($relation instanceof \Illuminate\Database\Eloquent\Collection) {
                if(empty($relations->get('relations')->get($key)))
                    $relations->get('relations')->put($key, collect([]));

                $collection = $this->newInstance($transformer)->runTransformData($relation)->getResponse();

                $collection->get('data')->first()->each(function($item) use(&$key, &$relations) {
                    $parseRelation = $this->parseRelation($item);
                    if(empty($relations->get('relations')->get($key)->get('data')))
                        $relations->get('relations')->get($key)->put('data', collect([$parseRelation->get('data')]));
                    else
                        $relations->get('relations')->get($key)->put('data', $relations->get('relations')->get($key)->get('data')->merge(collect([$parseRelation->get('data')])));
                });

                $this->addIncluded($collection);
                continue;
            }

            $instanceRelation = $this->newInstance($transformer)->runTransformData($relation)->getResponse();

            // В случае если это зависимость к которой кастуем, начинаем кастование
            if ($this->castTransformer && $this->castTransformer instanceof $transformer) {
                $relations->put('cast', $instanceRelation->get('data')->first());
                $this->addIncluded($instanceRelation);
                continue;
            }

            $relations->get('relations')->put($key, $this->parseRelation($instanceRelation));
        }
        return $relations;
    }

    protected function parseRelation($relation) : Collection
    {
        $return = collect([
            'data' => []
        ]);

        $firstRelation = empty($relation->get('data')) ? $relation : $relation->get('data')->first();
        $return->put('data', [
            'type' => $firstRelation->get('type'),
            'id' => $firstRelation->get('id')
        ]);

        $this->addIncluded($relation, [$firstRelation]);

        return $return;
    }

    protected function addIncluded($fullRelation, $includeRelation = array()) : ModelTransformer
    {
        if(empty($this->response->get(Transformer::ATTR_INCLUDES)))
            $this->response->put(Transformer::ATTR_INCLUDES, collect([]));

        if(count($includeRelation)) {
            foreach($includeRelation as $relation) {
                $key = $relation->get('type') . $relation->get('id');
                if(empty($this->response->get(Transformer::ATTR_INCLUDES)->get($key)))
                    $this->response->get(Transformer::ATTR_INCLUDES)->put($key, $relation);
                else
                    $this->response->get(Transformer::ATTR_INCLUDES)->put($key, $this->mergeModels($this->response->get(Transformer::ATTR_INCLUDES)->get($key), $relation));
            }
        }

        if(!empty($fullRelation->get(Transformer::ATTR_INCLUDES)))
            $this->response->put(Transformer::ATTR_INCLUDES, $this->response->get(Transformer::ATTR_INCLUDES)->merge($fullRelation->get(Transformer::ATTR_INCLUDES)));

        return $this;
    }

    protected function mergeModels($relation, $newRelation) : Collection
    {
        $mergeData = [
            Transformer::ATTR_IDENTIFIER,
            Transformer::ATTR_ATTRIBUTES,
            Transformer::ATTR_RELATIONSHIP,
            Transformer::ATTR_LINKS
        ];

        foreach($mergeData as $type) {
            if(!empty($newRelation->get($type)) && !empty($relation->get($type)) && $relation->get($type) instanceof Collection) {
                $relation->put($type, $relation->get($type)->merge($newRelation->get($type)));
                continue;
            }

            if(!empty($newRelation->get($type)))
                $relation->put($type, $newRelation->get($type));
        }

        unset($mergeData);
        return $relation;
    }

    protected function parseLinks($model) : Collection
    {
        return collect($this->transformer->getUrls())->transform(function($rule) use(&$model) {
            $routeName = $rule['name'];
            unset($rule['name']);

            $routeParam = [];
            foreach($rule as $routeKey => $modelAttribute) {
                if(!Str::contains($routeKey, "as_")) continue;
                $routeParam[ltrim($routeKey, 'as_')] = $model->get($modelAttribute) ?: null;
            }

            return route($routeName, $routeParam);
        });
    }

    protected function parseMeta($model)
    {
        if(!count($this->transformer->getMeta()) || !method_exists($model, 'getMeta') || !count($model->getMeta()))
            return false;

        $this->response->put(Transformer::ATTR_META, $model->getMeta());
    }
}
