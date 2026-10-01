<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderTaskRequest extends FormRequest
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
            // Required: a drop always names the column it landed in. The column the card came from
            // is not a default worth guessing, because guessing wrong moves a task silently.
            'status' => ['required', Rule::enum(TaskStatus::class)],
            // The task the dropped card should follow, and null for the top of the column. Not an
            // index: the client counts the cards it drew, and a filter can hide some of them, so an
            // index of 1 on screen is not position 1 in the column. Naming the card the user
            // dropped onto is the one thing both ends agree about.
            'after' => ['present', 'nullable', 'integer', Rule::exists('tasks', 'id')->whereNull('deleted_at')],
        ];
    }
}
