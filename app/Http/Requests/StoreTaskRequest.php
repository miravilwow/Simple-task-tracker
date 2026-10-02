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
            // It is required on create: a task nobody has given a day to is one the calendar,
            // Today, Upcoming and Overdue all have nothing to say about. `schedule` still takes
            // null, because clearing a date is how a task is dragged back to the calendar's tray.
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }
}
