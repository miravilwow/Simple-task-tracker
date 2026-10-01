<?php

namespace App\Http\Requests;

use App\Enums\CategoryColor;
use App\Enums\CategoryIcon;
use App\Models\Category;
use App\Rules\NotItsOwnDescendant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
            // ignore(), or renaming a project without touching its name would collide with itself.
            'name' => [
                'required',
                'string',
                'max:40',
                Rule::unique('categories', 'name')->ignore($category),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['required', Rule::enum(CategoryColor::class)],
            'icon' => ['required', Rule::enum(CategoryIcon::class)],
            // present, so clearing the parent is an explicit null rather than a forgotten key.
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
