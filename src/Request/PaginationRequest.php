<?php

namespace lemax10\JsonTransformer\Request;

use Illuminate\Foundation\Http\FormRequest;
use Request;

class PaginationRequest extends FormRequest
{
	protected $pagination = true;

	public function getPagination()
	{
		return $this->pagination;
	}

	public function noPaginate()
	{
		$this->pagination = false;
		return $this;
	}

	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @return bool
	 */
	public function authorize()
	{
		return true;
	}

	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules()
	{
		return [
			'page.number' => 'sometimes|required|integer',
			'page.size' => 'sometimes|required|integer|max:999', // wtf blade
			'page.sort' => 'sometimes|required|string'
		];
	}
}
