<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\TaskSorter;

class TaskSorterTest extends TestCase
{
    private TaskSorter $sorter;

    protected function setUp(): void
    {
        $this->sorter = new TaskSorter;
    }

    public function test_sorts_high_before_medium_before_low(): void
    {
        $tasks = [
            ['id' => 1, 'priority' => 'low', 'created_at' => '2026-09-01 08:00:00'],
            ['id' => 2, 'priority' => 'high', 'created_at' => '2026-09-01 08:00:00'],
            ['id' => 3, 'priority' => 'medium', 'created_at' => '2026-09-01 08:00:00'],
        ];

        $sorted = $this->sorter->sortTasks($tasks);

        $this->assertSame([2, 3, 1], array_column($sorted, 'id'));
    }

    public function test_sorts_older_tasks_first_when_priorities_match(): void
    {
        $tasks = [
            ['id' => 1, 'priority' => 'high', 'created_at' => '2026-09-03 08:00:00'],
            ['id' => 2, 'priority' => 'high', 'created_at' => '2026-09-01 08:00:00'],
            ['id' => 3, 'priority' => 'high', 'created_at' => '2026-09-02 08:00:00'],
        ];

        $sorted = $this->sorter->sortTasks($tasks);

        $this->assertSame([2, 3, 1], array_column($sorted, 'id'));
    }

    public function test_priority_outranks_date_in_mixed_list(): void
    {
        $tasks = [
            ['id' => 1, 'priority' => 'low', 'created_at' => '2026-09-01 08:00:00'],
            ['id' => 2, 'priority' => 'high', 'created_at' => '2026-09-05 08:00:00'],
            ['id' => 3, 'priority' => 'medium', 'created_at' => '2026-09-04 08:00:00'],
            ['id' => 4, 'priority' => 'high', 'created_at' => '2026-09-02 08:00:00'],
            ['id' => 5, 'priority' => 'medium', 'created_at' => '2026-09-03 08:00:00'],
        ];

        $sorted = $this->sorter->sortTasks($tasks);

        $this->assertSame([4, 2, 5, 3, 1], array_column($sorted, 'id'));
    }
}
