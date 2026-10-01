<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
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
        // Each field is optional, because the task dialog saves one at a time. category_id is
        // present but nullable, the same shape as schedule: null clears the project, while
        // omitting the key leaves it alone.
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', 'required', Rule::enum(TaskPriority::class)],
            'category_id' => ['sometimes', 'present', 'nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
        ];
    }
}
