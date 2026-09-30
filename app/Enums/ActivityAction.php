<?php

namespace App\Enums;

enum ActivityAction: string
{
    case Created = 'created';
    case Completed = 'completed';
    case Reopened = 'reopened';
    case Scheduled = 'scheduled';
    case Unscheduled = 'unscheduled';
    case Deleted = 'deleted';
    case Restored = 'restored';

    /**
     * The verb the activity feed reads out, past tense, with the task's title beside it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Added',
            self::Completed => 'Completed',
            self::Reopened => 'Reopened',
            self::Scheduled => 'Scheduled',
            self::Unscheduled => 'Cleared the date on',
            self::Deleted => 'Deleted',
            self::Restored => 'Restored',
        };
    }
}
