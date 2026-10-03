<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A card can carry one of eight preset colours, so the board is not all white.
 */
class TaskColorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_task_starts_without_a_color(): void
    {
        $this->postJson('/api/tasks', ['title' => 'Plain', 'priority' => 'low', 'due_date' => today()->toDateString(), 'due_time' => '09:00'])
            ->assertCreated()
            ->assertJsonPath('data.color', null);
    }

    public function test_color_is_not_set_on_create(): void
    {
        $this->postJson('/api/tasks', ['title' => 'Plain', 'priority' => 'low', 'color' => 'blue', 'due_date' => today()->toDateString(), 'due_time' => '09:00'])
            ->assertCreated()
            ->assertJsonPath('data.color', null);
    }

    public function test_show_returns_the_tasks_color(): void
    {
        $task = Task::factory()->create(['color' => 'green']);

        $this->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.color', 'green');
    }

    public function test_a_color_can_be_set_and_is_listed(): void
    {
        $task = Task::factory()->create();

        $this->patchJson("/api/tasks/{$task->id}", ['color' => 'blue'])
            ->assertOk()
            ->assertJsonPath('data.color', 'blue');

        $this->assertSame('blue', $this->getJson('/api/tasks')->json('data.0.color'));
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'color' => 'blue']);
    }

    public function test_null_clears_the_color(): void
    {
        $task = Task::factory()->create(['color' => 'pink']);

        $this->patchJson("/api/tasks/{$task->id}", ['color' => null])
            ->assertOk()
            ->assertJsonPath('data.color', null);
    }

    public function test_an_empty_color_clears_it(): void
    {
        $task = Task::factory()->create(['color' => 'pink']);

        $this->patchJson("/api/tasks/{$task->id}", ['color' => ''])->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'color' => null]);
    }

    public function test_an_unknown_color_is_a_400(): void
    {
        $task = Task::factory()->create();

        $this->patchJson("/api/tasks/{$task->id}", ['color' => 'black'])
            ->assertStatus(400)
            ->assertJsonValidationErrors('color');
    }

    public function test_patching_another_field_keeps_the_color(): void
    {
        $task = Task::factory()->create(['color' => 'teal']);

        $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Renamed'])->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'color' => 'teal']);
    }
}
