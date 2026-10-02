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
        $this->sorter = new TaskSorter();
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

    public function test_it_does_not_change_the_array_it_was_given(): void
    {
        $tasks = [
            ['id' => 1, 'priority' => 'low', 'created_at' => '2026-09-01 08:00:00'],
            ['id' => 2, 'priority' => 'high', 'created_at' => '2026-09-01 08:00:00'],
        ];
        $original = $tasks;

        $sorted = $this->sorter->sortTasks($tasks);

        // usort rearranges whatever it is handed, and it is handed the copy PHP makes when an
        // array is passed by value. The caller's own array has to come back untouched, so a
        // caller can sort a list twice, or sort it and still report what it started with.
        $this->assertSame($original, $tasks);
        $this->assertSame([2, 1], array_column($sorted, 'id'));
    }

    public function test_it_compares_timestamps_rather_than_the_raw_strings(): void
    {
        // Two ways of writing a date that sort one way as text and the other way as time.
        // "2026-09-02" sorts before "September 1" as plain text, because "2" comes before "S",
        // so a sorter comparing the strings would answer this backwards.
        $tasks = [
            ['id' => 1, 'priority' => 'high', 'created_at' => '2026-09-02 08:00:00'],
            ['id' => 2, 'priority' => 'high', 'created_at' => 'September 1, 2026 08:00:00'],
        ];

        $sorted = $this->sorter->sortTasks($tasks);

        $this->assertSame([2, 1], array_column($sorted, 'id'));
    }

    public function test_an_empty_list_sorts_to_an_empty_list(): void
    {
        $this->assertSame([], $this->sorter->sortTasks([]));
    }

    public function test_it_returns_a_list_with_keys_in_order_from_zero(): void
    {
        // The result is serialised straight to JSON by the API. usort renumbers the keys, and a
        // gap in them would turn the list into a JSON object and break every client reading it
        // as an array.
        $sorted = $this->sorter->sortTasks([
            ['id' => 1, 'priority' => 'low', 'created_at' => '2026-09-01 08:00:00'],
            ['id' => 2, 'priority' => 'high', 'created_at' => '2026-09-01 08:00:00'],
        ]);

        $this->assertSame([0, 1], array_keys($sorted));
    }
}
