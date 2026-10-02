<?php

namespace App\Enums;

/**
 * What a selection of tasks is being asked to do.
 *
 * The four are the two the table's selection toolbar offers and the two that undo them. Starting a
 * task is deliberately absent: picking work up is something you do to one task at a time, and a
 * toolbar button for it would be a gesture nobody makes.
 */
enum BulkAction: string
{
    case Complete = 'complete';
    case Reopen = 'reopen';
    case Delete = 'delete';
    case Restore = 'restore';

    /**
     * The stage this action moves a task to, or null when it is not a stage change at all.
     *
     * Deleting and restoring are the two that are not, and they are told apart here rather than in
     * the controller so the controller has one question to ask instead of four.
     */
    public function status(): ?TaskStatus
    {
        return match ($this) {
            self::Complete => TaskStatus::Completed,
            self::Reopen => TaskStatus::Pending,
            self::Delete, self::Restore => null,
        };
    }

    /** The action that puts back what this one did, which is what the toast's Undo sends. */
    public function reverse(): self
    {
        return match ($this) {
            self::Complete => self::Reopen,
            self::Reopen => self::Complete,
            self::Delete => self::Restore,
            self::Restore => self::Delete,
        };
    }
}
