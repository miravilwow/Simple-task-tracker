<?php

namespace App\Enums;

enum ActivityAction: string
{
    case Created = 'created';
    case Completed = 'completed';
    case Started = 'started';
    case Reopened = 'reopened';
    case Scheduled = 'scheduled';
    case Unscheduled = 'unscheduled';
    case Deleted = 'deleted';
    case Updated = 'updated';
    case Restored = 'restored';

    /**
     * The verb the activity feed reads out, past tense, with the task's title beside it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Added',
            self::Completed => 'Completed',
            self::Started => 'Started',
            self::Reopened => 'Reopened',
            self::Scheduled => 'Scheduled',
            self::Unscheduled => 'Cleared the date on',
            self::Deleted => 'Deleted',
            self::Updated => 'Edited',
            self::Restored => 'Restored',
        };
    }
}
