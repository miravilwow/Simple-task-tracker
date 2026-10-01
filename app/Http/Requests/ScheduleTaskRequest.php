<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ScheduleTaskRequest extends FormRequest
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
        // Present but nullable: sending null is how the UI clears a due date. The same past-date
        // rule as create, or dragging a chip onto a past day would be the way around it.
        return [
            'due_date' => ['present', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }
}
