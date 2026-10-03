<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The Deleted view: `GET /api/tasks?trashed=1` backs its table, `stats.deleted` backs its
 * sidebar badge, and `restore` is its one row action. The exam's four endpoints do not name any
 * of this; it exists so a `DELETE` has somewhere to be undone from after the toast is gone.
 */
class TrashedTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_trashed_lists_only_soft_deleted_tasks(): void
    {
        $kept = Task::factory()->create(['title' => 'Still here']);
        $removed = Task::factory()->create(['title' => 'Gone']);
        $removed->delete();

        $this->getJson('/api/tasks?trashed=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $removed->id);

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $kept->id);
    }

    public function test_trashed_orders_newest_deleted_first(): void
    {
        Carbon::setTestNow('2026-09-30 09:00:00');
        $first = Task::factory()->create(['title' => 'Deleted first']);
        $first->delete();

        Carbon::setTestNow('2026-09-30 09:05:00');
        $second = Task::factory()->create(['title' => 'Deleted second']);
        $second->delete();

        $this->getJson('/api/tasks?trashed=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id);
    }

    public function test_trashed_rejects_combining_with_another_filter(): void
    {
        $this->getJson('/api/tasks?trashed=1&status=pending')
            ->assertStatus(400)
            ->assertJsonValidationErrors('trashed');
    }

    public function test_stats_counts_trashed_tasks_separately(): void
    {
        Task::factory()->count(2)->create();
        Task::factory()->create()->delete();

        $this->getJson('/api/tasks/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.deleted', 1);
    }

    public function test_a_trashed_task_can_be_read_but_not_edited(): void
    {
        $task = Task::factory()->create(['title' => 'Gone']);
        $task->delete();

        $this->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Gone')
            ->assertJsonPath('data.deleted_at', fn ($value) => $value !== null);

        $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Edited'])->assertNotFound();
        $this->patchJson("/api/tasks/{$task->id}/complete")->assertNotFound();
    }

    public function test_restore_brings_a_trashed_task_back_into_the_live_list(): void
    {
        $task = Task::factory()->create();
        $task->delete();

        $this->patchJson("/api/tasks/{$task->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id);

        $this->getJson('/api/tasks')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/tasks?trashed=1')->assertOk()->assertJsonCount(0, 'data');
    }
}
