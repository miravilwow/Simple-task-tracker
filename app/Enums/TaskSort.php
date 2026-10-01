<?php

namespace App\Enums;

enum TaskSort: string
{
    // Default is Src\TaskSorter: priority, then oldest first. The other two replace it entirely.
    case Default = 'default';
    case DueDate = 'due';
    case Name = 'name';

    // What someone arranged by hand on the board. The one order a sort cannot work out.
    case Manual = 'manual';
}
