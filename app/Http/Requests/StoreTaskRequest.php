<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'category_name' => ['nullable', 'string', 'max:40'],
            // A due date is a promise about work still ahead, so it cannot be set in the past.
            // A task still becomes overdue the ordinary way, by the day arriving and passing.
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }
}
