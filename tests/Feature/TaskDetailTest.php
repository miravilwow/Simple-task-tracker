<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The task dialog: reading one task with its sub-tasks, editing its fields, and the sub-task
 * checklist itself.
 */
class TaskDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_one_task_with_its_subtasks(): void
    {
        $task = Task::factory()->create(['title' => 'Write the README']);
        $task->subtasks()->create(['title' => 'Setup steps', 'position' => 1]);
        $task->subtasks()->create(['title' => 'API table', 'position' => 2, 'is_done' => true]);

        $response = $this->getJson("/api/tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('data.title', 'Write the README')
            ->assertJsonCount(2, 'data.subtasks')
            ->assertJsonPath('data.subtasks.0.title', 'Setup steps')
            ->assertJsonPath('data.subtasks.1.is_done', true);
    }

    public function test_show_returns_404_for_a_missing_task(): void
    {
        $this->getJson('/api/tasks/999')->assertNotFound();
    }

    public function test_the_list_does_not_carry_subtasks(): void
    {
        $task = Task::factory()->create();
        $task->subtasks()->create(['title' => 'Hidden']);

        // A sub-task is a checklist item on one task, so it has no business on every row.
        $this->getJson('/api/tasks')->assertOk()->assertJsonMissingPath('data.0.subtasks');
    }

    public function test_updates_the_fields_the_dialog_edits(): void
    {
        $task = Task::factory()->create(['title' => 'Old', 'priority' => 'low']);
        $response = $this->patchJson("/api/tasks/{$task->id}", [
            'title' => 'New',
            'description' => 'Now with a description.',
            'priority' => 'high',
            'category_name' => 'Errands',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'New')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.category.name', 'Errands');
    }

    public function test_updates_one_field_without_touching_the_others(): void
    {
        $task = Task::factory()->create(['title' => 'Keep me', 'priority' => 'high']);

        $this->patchJson("/api/tasks/{$task->id}", ['priority' => 'low'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Keep me')
            ->assertJsonPath('data.priority', 'low');
    }

    public function test_clears_a_project_with_null(): void
    {
        $task = Task::factory()->for(Category::factory())->create();

        $this->patchJson("/api/tasks/{$task->id}", ['category_name' => null])
            ->assertOk()
            ->assertJsonPath('data.category', null);
    }

    public function test_update_rejects_an_empty_title(): void
    {
        $task = Task::factory()->create();

        $this->patchJson("/api/tasks/{$task->id}", ['title' => ''])
            ->assertStatus(400)
            ->assertJsonValidationErrors('title');
    }

    public function test_update_cannot_change_the_status(): void
    {
        $task = Task::factory()->create(['status' => 'pending']);

        // Status moves only through start, complete and reopen, each of which logs what happened.
        $this->patchJson("/api/tasks/{$task->id}", ['status' => 'completed'])->assertOk();

        $this->assertSame('pending', $task->fresh()->status->value);
    }

    public function test_adds_a_subtask(): void
    {
        $task = Task::factory()->create();

        $this->postJson("/api/tasks/{$task->id}/subtasks", ['title' => 'First step'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'First step')
            ->assertJsonPath('data.is_done', false);
    }

    public function test_subtasks_keep_the_order_they_were_written_in(): void
    {
        $task = Task::factory()->create();

        foreach (['One', 'Two', 'Three'] as $title) {
            $this->postJson("/api/tasks/{$task->id}/subtasks", ['title' => $title])->assertCreated();
        }

        $this->assertSame(['One', 'Two', 'Three'], $task->subtasks()->pluck('title')->all());
    }

    public function test_rejects_an_empty_subtask(): void
    {
        $task = Task::factory()->create();

        $this->postJson("/api/tasks/{$task->id}/subtasks", ['title' => '  '])
            ->assertStatus(400)
            ->assertJsonValidationErrors('title');
    }

    public function test_ticks_a_subtask_off(): void
    {
        $task = Task::factory()->create();
        $subtask = $task->subtasks()->create(['title' => 'Step']);

        $this->patchJson("/api/tasks/{$task->id}/subtasks/{$subtask->id}", ['is_done' => true])
            ->assertOk()
            ->assertJsonPath('data.is_done', true);
    }

    public function test_deletes_a_subtask(): void
    {
        $task = Task::factory()->create();
        $subtask = $task->subtasks()->create(['title' => 'Step']);

        $this->deleteJson("/api/tasks/{$task->id}/subtasks/{$subtask->id}")->assertOk();

        $this->assertDatabaseMissing('subtasks', ['id' => $subtask->id]);
    }

    public function test_a_subtask_cannot_be_reached_through_another_task(): void
    {
        $task = Task::factory()->create();
        $other = Task::factory()->create();
        $subtask = $task->subtasks()->create(['title' => 'Step']);

        $this->deleteJson("/api/tasks/{$other->id}/subtasks/{$subtask->id}")->assertNotFound();
    }

    public function test_deleting_a_task_takes_its_subtasks(): void
    {
        $task = Task::factory()->create();
        $task->subtasks()->create(['title' => 'Step']);

        // Soft delete leaves the row, so the checklist survives an Undo.
        $this->deleteJson("/api/tasks/{$task->id}")->assertOk();

        $this->assertDatabaseCount('subtasks', 1);
    }

    public function test_an_in_progress_task_past_its_due_date_is_still_overdue(): void
    {
        $task = Task::factory()->create(['status' => 'in_progress', 'due_date' => today()->subDay()]);

        $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('data.is_overdue', true);
    }
}
