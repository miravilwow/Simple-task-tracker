<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Completed = 'completed';

    /**
     * What the board column and the badge call it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'To do',
            self::InProgress => 'In progress',
            self::InReview => 'In review',
            self::Completed => 'Done',
        };
    }

    /**
     * To do, In progress and In review are all unfinished: a task is unfinished until it is completed.
     *
     * The smart views are work queues, so they ask this rather than naming Pending: starting a
     * task must not drop it out of Today, which is exactly where its owner expects to find the
     * thing they are in the middle of.
     *
     * @return array<int, string>
     */
    public static function unfinished(): array
    {
        return [self::Pending->value, self::InProgress->value, self::InReview->value];
    }
}
