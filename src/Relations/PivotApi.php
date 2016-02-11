<?php
namespace lemax10\JsonTransformer\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PivotApi extends Pivot
{
    public function getParent()
    {
        return $this->parent;
    }
}