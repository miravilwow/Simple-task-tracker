<?php

namespace App\Enums;

enum DueFilter: string
{
    case Overdue = 'overdue';
    case Today = 'today';
    case Upcoming = 'upcoming';
}
