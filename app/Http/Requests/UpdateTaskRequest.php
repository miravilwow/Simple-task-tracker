<?php

namespace App\Http\Requests;

use App\Enums\TaskColor;
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
        // Each field is optional, because the task dialog saves one at a time. category_name
        // null or empty clears the project, while omitting the key leaves it alone.
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', 'required', Rule::enum(TaskPriority::class)],
            'color' => ['sometimes', 'nullable', Rule::enum(TaskColor::class)],
            'category_name' => ['sometimes', 'nullable', 'string', 'max:40'],
            // The dialog is the only way to a time: the date has the calendar's drag, and a time
            // has no gesture of its own. A time needs a day to sit on, so setting one on a task
            // with no date is refused rather than quietly dropped; clearing one always works.
            'due_time' => [
                'sometimes',
                'nullable',
                'date_format:H:i',
                Rule::prohibitedIf(
                    fn () => $this->input('due_time') !== null && $this->route('task')?->due_date === null
                ),
            ],
        ];
    }
}
