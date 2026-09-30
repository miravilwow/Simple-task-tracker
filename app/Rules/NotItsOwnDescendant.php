<?php

namespace App\Rules;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A project may not be moved under itself or under one of its own children.
 *
 * Allowing it would cut the whole branch off the tree: every project in it would still have a
 * parent, but none of them would be reachable from the top level, so the sidebar would simply
 * stop showing them.
 */
class NotItsOwnDescendant implements ValidationRule
{
    public function __construct(private readonly Category $category)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (in_array((int) $value, $this->category->descendantIds(), true)) {
            $fail('A project cannot be moved inside itself.');
        }
    }
}
