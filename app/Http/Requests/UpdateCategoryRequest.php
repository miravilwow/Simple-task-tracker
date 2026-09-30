<?php

namespace App\Http\Requests;

use App\Enums\CategoryIcon;
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
        return [
            // ignore(), or renaming a project without touching its name would collide with itself.
            'name' => [
                'required',
                'string',
                'max:40',
                Rule::unique('categories', 'name')->ignore($this->route('category')),
            ],
            'icon' => ['required', Rule::enum(CategoryIcon::class)],
        ];
    }
}
