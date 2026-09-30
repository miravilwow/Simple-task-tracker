<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_tasks_sorted_by_priority_then_oldest_first(): void
    {
        $lowOld = Task::factory()->create(['priority' => 'low', 'created_at' => '2026-09-01 08:00:00']);
        $highNew = Task::factory()->create(['priority' => 'high', 'created_at' => '2026-09-03 08:00:00']);
        $highOld = Task::factory()->create(['priority' => 'high', 'created_at' => '2026-09-02 08:00:00']);

        $response = $this->getJson('/api/tasks');

        $response->assertOk();
        $this->assertSame(
            [$highOld->id, $highNew->id, $lowOld->id],
            array_column($response->json('data'), 'id')
        );
    }

    public function test_filters_tasks_by_status(): void
    {
        $pending = Task::factory()->create();
        $completed = Task::factory()->completed()->create();

        $this->getJson('/api/tasks?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id);

        $this->getJson('/api/tasks?status=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $completed->id);
    }

    public function test_rejects_invalid_status_filter(): void
    {
        $this->getJson('/api/tasks?status=archived')
            ->assertStatus(400)
            ->assertJsonValidationErrors('status');
    }

    public function test_creates_a_task(): void
    {
        $response = $this->postJson('/api/tasks', [
            'title' => 'Write README',
            'description' => 'Setup steps and AI disclosure',
            'priority' => 'high',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Write README')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('tasks', ['title' => 'Write README', 'status' => 'pending']);
    }

    public function test_rejects_blank_title(): void
    {
        $this->postJson('/api/tasks', ['title' => '   ', 'priority' => 'low'])
            ->assertStatus(400)
            ->assertJsonValidationErrors('title');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_rejects_invalid_priority(): void
    {
        $this->postJson('/api/tasks', ['title' => 'Valid title', 'priority' => 'urgent'])
            ->assertStatus(400)
            ->assertJsonValidationErrors('priority');
    }

    public function test_marks_a_task_as_completed(): void
    {
        $task = Task::factory()->create();

        $this->patchJson("/api/tasks/{$task->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame(TaskStatus::Completed, $task->fresh()->status);
    }

    public function test_complete_returns_404_for_missing_task(): void
    {
        $this->patchJson('/api/tasks/999/complete')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Task not found.']);
    }

    public function test_deletes_a_task(): void
    {
        $task = Task::factory()->create();

        $this->deleteJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Task deleted.');

        $this->assertModelMissing($task);
    }

    public function test_delete_returns_404_for_missing_task(): void
    {
        $this->deleteJson('/api/tasks/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Task not found.']);
    }

    public function test_returns_task_statistics(): void
    {
        Task::factory()->count(2)->create(['priority' => TaskPriority::High]);
        Task::factory()->create(['priority' => TaskPriority::Low]);
        Task::factory()->completed()->create(['priority' => TaskPriority::High]);

        $this->getJson('/api/tasks/stats')
            ->assertOk()
            ->assertExactJson(['data' => [
                'total' => 4,
                'pending' => 3,
                'completed' => 1,
                'high_priority_pending' => 2,
            ]]);
    }

    public function test_statistics_are_zero_when_there_are_no_tasks(): void
    {
        $this->getJson('/api/tasks/stats')
            ->assertOk()
            ->assertExactJson(['data' => [
                'total' => 0,
                'pending' => 0,
                'completed' => 0,
                'high_priority_pending' => 0,
            ]]);
    }

    public function test_api_is_rate_limited_to_60_requests_per_minute(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/tasks/stats')->assertOk();
        }

        $this->getJson('/api/tasks/stats')->assertTooManyRequests();
    }

    public function test_seeder_creates_demo_tasks(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('tasks', 8);
        $this->assertDatabaseHas('tasks', ['status' => TaskStatus::Completed->value]);
    }
}
