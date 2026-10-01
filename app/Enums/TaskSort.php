<?php

namespace App\Enums;

enum TaskSort: string
{
    // Default is Src\TaskSorter: priority, then oldest first. The other two replace it entirely.
    case Default = 'default';
    case DueDate = 'due';
    case Name = 'name';
}
