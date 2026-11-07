<?php

namespace App\Http\Requests\Business;

use App\Http\Requests\ReportFilterRequest;
use Illuminate\Validation\Rule;

class ListRequest extends ReportFilterRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted', 'active', 'archived', 'void'])], 'category_id' => ['nullable', 'integer'], 'kind' => ['nullable', Rule::in(['product', 'service'])]]);
    }
}
