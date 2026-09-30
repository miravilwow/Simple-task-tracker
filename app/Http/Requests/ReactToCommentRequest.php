<?php

namespace App\Http\Requests;

use App\Enums\Reaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReactToCommentRequest extends FormRequest
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
            // A closed set, so the value written into the page can never come from the client.
            'emoji' => ['required', Rule::enum(Reaction::class)],
            // Idempotent rather than a toggle, the same shape as favouriting a project: two taps
            // racing each other would otherwise undo one another.
            'reacted' => ['required', 'boolean'],
            // The browser's own token. Opaque and random; it identifies a browser, not a person,
            // because the app has no accounts.
            'reactor' => ['required', 'string', 'max:64'],
        ];
    }
}
