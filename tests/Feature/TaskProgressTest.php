<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The middle column of the board: a task that has been started but is not finished.
 */
class TaskProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_task_moves_it_into_progress(): void
    {
        $task = Task::factory()->create(['status' => 'pending']);

        $this->patchJson("/api/tasks/{$task->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress']);
    }

    public function test_a_started_task_can_be_finished_and_reopened_to_the_first_column(): void
    {
        $task = Task::factory()->create(['status' => 'in_progress']);

        $this->patchJson("/api/tasks/{$task->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        // Reopen puts a task back at the start, not back into progress: it is the undo for
        // finishing, and the board's own columns are how you say "I am working on this".
        $this->patchJson("/api/tasks/{$task->id}/reopen")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_starting_a_task_keeps_it_in_the_work_queues(): void
    {
        // The whole point: a task you are in the middle of is the one you are most likely
        // looking for, so starting it must not drop it out of Today.
        $task = Task::factory()->create(['due_date' => today(), 'status' => 'pending']);

        $this->patchJson("/api/tasks/{$task->id}/start")->assertOk();

        $this->assertCount(1, $this->getJson('/api/tasks?due=today')->json('data'));
        $this->assertCount(1, $this->getJson('/api/tasks?due=upcoming')->json('data'));
    }

    public function test_an_overdue_task_stays_overdue_once_started(): void
    {
        $task = Task::factory()->create(['due_date' => today()->subDay(), 'status' => 'pending']);

        $this->patchJson("/api/tasks/{$task->id}/start")->assertOk();

        $this->assertCount(1, $this->getJson('/api/tasks?due=overdue')->json('data'));
    }

    public function test_the_unfinished_count_covers_both_open_columns(): void
    {
        Task::factory()->create(['status' => 'pending']);
        Task::factory()->create(['status' => 'in_progress']);
        Task::factory()->create(['status' => 'completed']);

        $stats = $this->getJson('/api/tasks/stats')->json('data');

        // Starting a task is not finishing it, so it must not move the number that says how
        // much is left.
        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['pending']);
        $this->assertSame(1, $stats['completed']);
    }

    public function test_the_high_priority_count_covers_both_open_columns(): void
    {
        Task::factory()->create(['status' => 'in_progress', 'priority' => 'high']);
        Task::factory()->create(['status' => 'completed', 'priority' => 'high']);

        $this->assertSame(1, $this->getJson('/api/tasks/stats')->json('data.high_priority_pending'));
    }

    public function test_the_due_today_badge_matches_the_today_view(): void
    {
        Task::factory()->create(['due_date' => today(), 'status' => 'in_progress']);
        Task::factory()->create(['due_date' => today(), 'status' => 'completed']);

        $this->assertSame(1, $this->getJson('/api/tasks/stats')->json('data.due_today'));
        $this->assertCount(1, $this->getJson('/api/tasks?due=today')->json('data'));
    }

    public function test_the_list_reads_to_do_then_in_progress_then_done(): void
    {
        // All the same priority and age, so only the stage decides the order. It is the order the
        // board's columns read in too, because they are the same three stages.
        $made = now()->subDay();
        Task::factory()->create(['title' => 'Done', 'status' => 'completed', 'priority' => 'high', 'created_at' => $made]);
        Task::factory()->create(['title' => 'To do', 'status' => 'pending', 'priority' => 'high', 'created_at' => $made]);
        Task::factory()->create(['title' => 'Doing', 'status' => 'in_progress', 'priority' => 'high', 'created_at' => $made]);

        $titles = array_column($this->getJson('/api/tasks')->json('data'), 'title');

        $this->assertSame(['To do', 'Doing', 'Done'], $titles);
    }

    public function test_the_list_can_be_filtered_to_one_column(): void
    {
        Task::factory()->create(['title' => 'Doing', 'status' => 'in_progress']);
        Task::factory()->create(['title' => 'Waiting', 'status' => 'pending']);

        $titles = array_column($this->getJson('/api/tasks?status=in_progress')->json('data'), 'title');

        $this->assertSame(['Doing'], $titles);
    }

    public function test_start_returns_404_for_a_missing_task(): void
    {
        $this->patchJson('/api/tasks/999/start')->assertNotFound();
    }

    public function test_a_task_still_starts_as_to_do(): void
    {
        // The exam pins the default, and widening the set must not move it.
        $this->postJson('/api/tasks', ['title' => 'Fresh', 'priority' => 'low'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');
    }
}
