<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_task_only_stamps_it(): void
    {
        $task = Task::factory()->create();

        $this->deleteJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Task deleted.');

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_restores_a_deleted_task(): void
    {
        $task = Task::factory()->create(['title' => 'Back from the dead']);
        $this->deleteJson("/api/tasks/{$task->id}")->assertOk();

        $this->patchJson("/api/tasks/{$task->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.title', 'Back from the dead');

        $this->assertNotSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_restore_returns_404_for_a_task_that_never_existed(): void
    {
        $this->patchJson('/api/tasks/999/restore')->assertNotFound();
    }

    public function test_deleting_the_same_task_twice_returns_404(): void
    {
        $task = Task::factory()->create();
        $this->deleteJson("/api/tasks/{$task->id}")->assertOk();

        $this->deleteJson("/api/tasks/{$task->id}")->assertNotFound();
    }

    public function test_a_deleted_task_disappears_from_the_list_and_comes_back_on_restore(): void
    {
        $task = Task::factory()->create(['title' => 'Temporary']);

        $this->deleteJson("/api/tasks/{$task->id}")->assertOk();
        $this->getJson('/api/tasks')->assertOk()->assertJsonCount(0, 'data');

        $this->patchJson("/api/tasks/{$task->id}/restore")->assertOk();
        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Temporary');
    }

    public function test_a_deleted_task_is_left_out_of_the_statistics(): void
    {
        // stats builds on toBase(); this pins that the soft-delete scope still reaches it.
        // The survivor is pinned to low and undated so only the deleted task can move the counts;
        // the factory randomises priority, which would otherwise make this flaky.
        Task::factory()->create(['priority' => 'low', 'due_date' => null]);
        $doomed = Task::factory()->create(['priority' => 'high', 'due_date' => today()]);

        $this->deleteJson("/api/tasks/{$doomed->id}")->assertOk();

        $this->getJson('/api/tasks/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.pending', 1)
            ->assertJsonPath('data.high_priority_pending', 0)
            ->assertJsonPath('data.due_today', 0);
    }

    public function test_a_deleted_task_is_left_out_of_its_category_count(): void
    {
        $category = Category::factory()->create();
        Task::factory()->create(['category_id' => $category->id]);
        $doomed = Task::factory()->create(['category_id' => $category->id]);

        $this->deleteJson("/api/tasks/{$doomed->id}")->assertOk();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.task_count', 1);
    }
}
