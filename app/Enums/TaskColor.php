<?php

namespace App\Enums;

/**
 * The colours a card can be tinted with. Null on the task is the default white.
 */
enum TaskColor: string
{
    case Red = 'red';
    case Orange = 'orange';
    case Yellow = 'yellow';
    case Green = 'green';
    case Teal = 'teal';
    case Blue = 'blue';
    case Purple = 'purple';
    case Pink = 'pink';
}
