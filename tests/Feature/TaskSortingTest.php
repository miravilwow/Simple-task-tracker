<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Display panel's Sorting and Priority filter. Default is Src\TaskSorter, which the exam
 * grades; Due date and Name replace it.
 */
class TaskSortingTest extends TestCase
{
    use RefreshDatabase;

    private function titles(string $query = ''): array
    {
        return array_column($this->getJson("/api/tasks{$query}")->assertOk()->json('data'), 'title');
    }

    public function test_default_sorting_is_the_task_sorter_order(): void
    {
        Task::factory()->create(['title' => 'Low', 'priority' => 'low']);
        Task::factory()->create(['title' => 'High', 'priority' => 'high']);
        Task::factory()->create(['title' => 'Medium', 'priority' => 'medium']);

        $this->assertSame(['High', 'Medium', 'Low'], $this->titles());
        $this->assertSame(['High', 'Medium', 'Low'], $this->titles('?sort=default'));
    }

    public function test_sorts_by_due_date_with_undated_tasks_last(): void
    {
        Task::factory()->create(['title' => 'Later', 'due_date' => today()->addWeek()]);
        Task::factory()->create(['title' => 'No date', 'due_date' => null]);
        Task::factory()->create(['title' => 'Soon', 'due_date' => today()]);

        $this->assertSame(['Soon', 'Later', 'No date'], $this->titles('?sort=due'));
    }

    public function test_sorts_by_name_ignoring_case(): void
    {
        Task::factory()->create(['title' => 'banana']);
        Task::factory()->create(['title' => 'Apple']);
        Task::factory()->create(['title' => 'cherry']);

        $this->assertSame(['Apple', 'banana', 'cherry'], $this->titles('?sort=name'));
    }

    public function test_every_sort_still_puts_unfinished_work_first(): void
    {
        Task::factory()->create(['title' => 'Done', 'status' => 'completed', 'due_date' => today()]);
        Task::factory()->create(['title' => 'Doing', 'status' => 'in_progress', 'due_date' => today()->addMonth()]);

        // Sorting by date would otherwise bury today's live task under a finished one.
        $this->assertSame(['Doing', 'Done'], $this->titles('?sort=due'));
    }

    public function test_rejects_an_unknown_sort(): void
    {
        $this->getJson('/api/tasks?sort=sideways')
            ->assertStatus(400)
            ->assertJsonValidationErrors('sort');
    }

    public function test_filters_by_priority(): void
    {
        Task::factory()->create(['title' => 'Urgent', 'priority' => 'high']);
        Task::factory()->create(['title' => 'Later', 'priority' => 'low']);

        $this->assertSame(['Urgent'], $this->titles('?priority=high'));
    }

    public function test_the_completed_toggle_hides_finished_work(): void
    {
        Task::factory()->create(['title' => 'Open', 'status' => 'pending']);
        Task::factory()->create(['title' => 'Started', 'status' => 'in_progress']);
        Task::factory()->create(['title' => 'Finished', 'status' => 'completed']);

        $this->assertSame(['Started', 'Open'], $this->titles('?completed=0'));
        $this->assertCount(3, $this->titles('?completed=1'));
    }

    public function test_rejects_a_status_and_a_completed_toggle_together(): void
    {
        $this->getJson('/api/tasks?status=completed&completed=0')
            ->assertStatus(400)
            ->assertJsonValidationErrors('completed');
    }

    public function test_rejects_an_unknown_priority(): void
    {
        $this->getJson('/api/tasks?priority=urgent')
            ->assertStatus(400)
            ->assertJsonValidationErrors('priority');
    }
}
