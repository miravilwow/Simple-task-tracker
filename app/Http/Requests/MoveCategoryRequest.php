<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Rules\NotItsOwnDescendant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');

        return [
            // present|nullable: sending null moves the project back to the top level, while
            // omitting the key is a 400 rather than a silent no-op.
            'parent_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->whereNull('deleted_at'),
                new NotItsOwnDescendant($category),
            ],
        ];
    }
}
