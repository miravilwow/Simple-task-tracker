<?php

namespace App\Http\Requests;

use App\Enums\BulkAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTaskRequest extends FormRequest
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
        // Restore is the one action whose rows are the stamped ones. Every other action is asking
        // something of a live task, and a deleted id is then a 400 rather than a row quietly
        // skipped — the same refusal of a silent no-op the rest of the API makes.
        $restoring = $this->input('action') === BulkAction::Restore->value;

        $exists = $restoring
            ? Rule::exists('tasks', 'id')->whereNotNull('deleted_at')
            : Rule::exists('tasks', 'id')->whereNull('deleted_at');

        return [
            'action' => ['required', Rule::enum(BulkAction::class)],
            // Capped at 100. The endpoint saves each task on its own so that setStage() stays the
            // one path a status changes, so the cap is what keeps a single request from being unbounded
            // work. A table that can select more than 100 rows at once does not exist here.
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', $exists],
        ];
    }
}
