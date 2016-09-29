<?php
namespace lemax10\JsonTransformer\Response;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use lemax10\JsonApiTransformer\Mapper;
use lemax10\JsonTransformer\Request\PaginationRequest;
use lemax10\JsonTransformer\Transformer;

class PaginateTransformer extends AbstractTransformer {

    protected $request = false;
    protected $pageName = 'page';
    protected $pageNumber = 'number';
    protected $pageSize = 'size';
    protected $pageSort = 'sort';

    protected $defaultSort = false;
    protected $paginateQuery = false;

    public function __construct($transformer, $castTransformer, $merge, PaginationRequest $request, $defaultSort = false)
    {
        parent::__construct($transformer, $castTransformer, $merge);
        $this->request = $request;
        $this->defaultSort = $defaultSort;
        Paginator::currentPageResolver(function () {
            return $this->request->input(join('.', [$this->pageName, $this->pageNumber]), 1);
        });
    }

    public function runTransformPagination($query) : ModelTransformer
    {
        $this->paginateQuery = $this->parseSort($query);
        if($this->request->getPagination() !== false) {
            $this->paginateQuery = $this->paginateQuery->paginate($this->request->input(join('.', [$this->pageName, $this->pageSize]), 10));
        }

        if($this->paginateQuery instanceof LengthAwarePaginator)
            $transformData = $this->paginateQuery->getCollection();
        else if($this->paginateQuery instanceof Collection)
            $transformData = $this->paginateQuery;
        else
            $transformData = $this->paginateQuery->get();

        $responseModelTransformer = (new ModelTransformer($this->transformer, $this->castTransformer, $this->merge))->runTransformData($transformData);

        if($this->request->getPagination() !== false) {
            $responseModelTransformer->response->put(Transformer::ATTR_META, $this->getMeta())
                ->put(Transformer::ATTR_LINKS, $this->getNavigationLinks());
        }

        $responseModelTransformer->response->put(Transformer::ATTR_DATA, $responseModelTransformer->response->get(Transformer::ATTR_DATA)->shift());
        return $responseModelTransformer;
    }

    protected function getMeta() : array
    {
        return [
            'total-pages' => $this->paginateQuery->lastPage(),
            'page-size'   => $this->paginateQuery->perPage(),
            'currentPage' => $this->paginateQuery->currentPage(),
        ];
    }

    protected function getNavigationLinks() : array
    {
        $this->paginateQuery->setPageName('page[number]')->appends('page[size]', $this->paginateQuery->perPage());
        if($this->request->has(join('.', [$this->pageName, $this->pageSort])))
            $this->paginateQuery->appends('page[sort]', $this->request->input(join('.', [$this->pageName, $this->pageSort])));

        $links = [
            'self' => $this->paginateQuery->url($this->paginateQuery->currentPage()),
            'first' => $this->paginateQuery->url(1)
        ];
        if($this->paginateQuery->currentPage() > 1 && $this->paginateQuery->currentPage() <= $this->paginateQuery->lastPage())
            $links['prev'] = $this->paginateQuery->url($this->paginateQuery->currentPage() - 1);

        if($this->paginateQuery->hasMorePages())
            $links['next'] = $this->paginateQuery->nextPageUrl();

        $links['last'] = $this->paginateQuery->url($this->paginateQuery->lastPage());

        return $links;
    }

    private function parseSort($paginateQuery)
    {
        $sort = $this->defaultSort;
        if($this->request->has('page.sort'))
            $sort = $this->request->input('page.sort');

        if($sort !== false)
            $paginateQuery = $this->parseSorting($paginateQuery, $sort);

        return $paginateQuery;
    }

    private function parseSorting($query, $sort = '')
    {
        if(empty($sort))
            return $query;

        $lastKey = '';
        foreach(explode(',', $sort) as $attr) {
            if(empty($lastKey)) {
                $lastKey = static::uncamelcase($attr);
                continue;
            }

            $query = $query->orderBy($lastKey, in_array($attr, ['asc', 'desc']) ? $attr : 'desc');
            unset($lastKey);
        }

        return $query;
    }

    public static function uncamelcase($key, $delimeter="_") : string
    {
        return strtolower(preg_replace('/(?!^)[[:upper:]][[:lower:]]/', '$0', preg_replace('/(?!^)[[:upper:]]+/', $delimeter.'$0', $key)));
    }
}
