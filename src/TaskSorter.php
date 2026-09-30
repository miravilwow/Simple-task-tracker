<?php

declare(strict_types=1);

namespace Src;

class TaskSorter
{
    private const PRIORITY_WEIGHT = [
        'high' => 3,
        'medium' => 2,
        'low' => 1,
    ];

    /**
     * Sort by priority (high > medium > low), then by created_at (oldest first).
     *
     * @param  array<int, array{priority: string, created_at: string}>  $tasks
     * @return array<int, array{priority: string, created_at: string}>
     */
    public function sortTasks(array $tasks): array
    {
        usort($tasks, function (array $a, array $b): int {
            $byPriority = self::PRIORITY_WEIGHT[$b['priority']] <=> self::PRIORITY_WEIGHT[$a['priority']];

            if ($byPriority !== 0) {
                return $byPriority;
            }

            return strtotime($a['created_at']) <=> strtotime($b['created_at']);
        });

        return $tasks;
    }
}
