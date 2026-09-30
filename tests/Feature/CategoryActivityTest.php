<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_task_in_a_project_records_it(): void
    {
        $category = Category::factory()->create();

        $this->postJson('/api/tasks', [
            'title' => 'Write the report',
            'priority' => 'high',
            'category_id' => $category->id,
        ])->assertCreated();

        $this->getJson("/api/categories/{$category->id}/activity")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'created')
            ->assertJsonPath('data.0.label', 'Added')
            ->assertJsonPath('data.0.task_title', 'Write the report');
    }

    public function test_records_completing_reopening_deleting_and_restoring(): void
    {
        $category = Category::factory()->create();
        $task = Task::factory()->create(['category_id' => $category->id, 'title' => 'Ship it']);

        $this->patchJson("/api/tasks/{$task->id}/complete")->assertOk();
        $this->patchJson("/api/tasks/{$task->id}/reopen")->assertOk();
        $this->deleteJson("/api/tasks/{$task->id}")->assertOk();
        $this->patchJson("/api/tasks/{$task->id}/restore")->assertOk();

        $actions = array_column($this->getJson("/api/categories/{$category->id}/activity")->json('data'), 'action');

        // Newest first, so the feed reads in reverse of the order the actions happened.
        $this->assertSame(['restored', 'deleted', 'reopened', 'completed'], $actions);
    }

    public function test_setting_and_clearing_a_date_are_separate_entries(): void
    {
        $category = Category::factory()->create();
        $task = Task::factory()->create(['category_id' => $category->id]);

        $this->patchJson("/api/tasks/{$task->id}/schedule", ['due_date' => '2026-10-05'])->assertOk();
        $this->patchJson("/api/tasks/{$task->id}/schedule", ['due_date' => null])->assertOk();

        $actions = array_column($this->getJson("/api/categories/{$category->id}/activity")->json('data'), 'action');

        $this->assertSame(['unscheduled', 'scheduled'], $actions);
    }

    public function test_a_task_with_no_project_records_nothing(): void
    {
        $task = Task::factory()->create(['category_id' => null]);

        $this->patchJson("/api/tasks/{$task->id}/complete")->assertOk();

        $this->assertDatabaseCount('activities', 0);
    }

    public function test_an_entry_keeps_the_title_of_a_task_that_is_gone(): void
    {
        $category = Category::factory()->create();
        $task = Task::factory()->create(['category_id' => $category->id, 'title' => 'Cancel the trial']);

        $this->deleteJson("/api/tasks/{$task->id}")->assertOk();

        $this->getJson("/api/categories/{$category->id}/activity")
            ->assertJsonPath('data.0.task_title', 'Cancel the trial');
    }

    public function test_activity_only_covers_its_own_project(): void
    {
        $work = Category::factory()->create(['name' => 'Work']);
        $home = Category::factory()->create(['name' => 'Home']);
        Task::factory()->create(['category_id' => $home->id, 'title' => 'Wash up']);

        $this->patchJson("/api/tasks/{$this->taskIn($work, 'File taxes')->id}/complete")->assertOk();

        $titles = array_column($this->getJson("/api/categories/{$home->id}/activity")->json('data'), 'task_title');

        $this->assertNotContains('File taxes', $titles);
    }

    public function test_a_deleted_project_keeps_its_activity_for_a_restore(): void
    {
        $category = Category::factory()->create();
        $task = Task::factory()->create(['category_id' => $category->id]);
        $this->patchJson("/api/tasks/{$task->id}/complete")->assertOk();

        $this->deleteJson("/api/categories/{$category->id}")->assertOk();

        // A soft delete removes nothing, so the cascade never fires and the history survives.
        // It is only out of reach: the feed is bound to a project the default scope hides.
        $this->assertDatabaseCount('activities', 1);
        $this->getJson("/api/categories/{$category->id}/activity")->assertNotFound();

        $this->patchJson("/api/categories/{$category->id}/restore")->assertOk();
        $this->getJson("/api/categories/{$category->id}/activity")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'completed');
    }

    public function test_activity_returns_404_for_a_missing_project(): void
    {
        $this->getJson('/api/categories/999/activity')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Category not found.']);
    }

    private function taskIn(Category $category, string $title): Task
    {
        return Task::factory()->create(['category_id' => $category->id, 'title' => $title]);
    }
}
