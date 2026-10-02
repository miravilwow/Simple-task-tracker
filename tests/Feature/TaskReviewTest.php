<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The stage between doing the work and calling it done.
 */
class TaskReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_task_can_be_sent_to_review(): void
    {
        $task = Task::factory()->create(['status' => 'in_progress']);

        $this->patchJson("/api/tasks/{$task->id}/review")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_review']);
    }

    public function test_review_returns_404_for_a_missing_task(): void
    {
        $this->patchJson('/api/tasks/999/review')->assertNotFound();
    }

    public function test_a_task_in_review_is_still_unfinished(): void
    {
        Task::factory()->create(['status' => 'in_review', 'due_date' => today(), 'priority' => 'high']);
        Task::factory()->create(['status' => 'completed']);

        $stats = $this->getJson('/api/tasks/stats')->json('data');

        $this->assertSame(1, $stats['pending']);
        $this->assertSame(1, $stats['due_today']);
        $this->assertSame(1, $stats['high_priority_pending']);
        $this->assertCount(1, $this->getJson('/api/tasks?due=today')->json('data'));
    }

    public function test_the_list_puts_review_between_progress_and_done(): void
    {
        $made = now()->subDay();

        foreach (['completed' => 'Done', 'in_review' => 'Review', 'pending' => 'To do', 'in_progress' => 'Doing'] as $status => $title) {
            Task::factory()->create(['title' => $title, 'status' => $status, 'priority' => 'high', 'created_at' => $made]);
        }

        $titles = array_column($this->getJson('/api/tasks')->json('data'), 'title');

        $this->assertSame(['To do', 'Doing', 'Review', 'Done'], $titles);
    }

    public function test_reorder_moves_a_card_into_review(): void
    {
        $task = Task::factory()->create(['status' => 'in_progress']);

        $this->patchJson("/api/tasks/{$task->id}/reorder", ['status' => 'in_review', 'after' => null])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');

        $this->patchJson("/api/tasks/{$task->id}/reorder", ['status' => 'in_progress', 'after' => null])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_the_list_can_be_filtered_to_review(): void
    {
        Task::factory()->create(['title' => 'Review', 'status' => 'in_review']);
        Task::factory()->create(['title' => 'Doing', 'status' => 'in_progress']);

        $titles = array_column($this->getJson('/api/tasks?status=in_review')->json('data'), 'title');

        $this->assertSame(['Review'], $titles);
    }
}
