<?php

namespace App\Enums;

/**
 * The colours a project may carry beside its icon.
 *
 * Deliberately a short, closed set: the colour is a glance-level mark in a 16rem sidebar, and
 * a free colour field would let a user pick one that fails contrast against the panel.
 *
 * The matching Tailwind classes are literal strings in the PROJECT_COLORS map in
 * resources/js/app.js, the same way PRIORITY_BADGES works, because a class built at runtime is
 * one Tailwind cannot see. CategoryApiTest fails if the two drift apart.
 */
enum CategoryColor: string
{
    case Slate = 'slate';
    case Red = 'red';
    case Amber = 'amber';
    case Green = 'green';
    case Blue = 'blue';
    case Violet = 'violet';
    case Pink = 'pink';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
