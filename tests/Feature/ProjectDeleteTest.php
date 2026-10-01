<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting a project takes its tasks with it, and Undo brings back exactly the ones that went.
 */
class ProjectDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function titles(): array
    {
        return array_column($this->getJson('/api/tasks')->assertOk()->json('data'), 'title');
    }

    public function test_deleting_a_project_hides_its_tasks_from_every_list(): void
    {
        $category = Category::factory()->create();
        Task::factory()->create(['category_id' => $category->id, 'title' => 'Practice', 'status' => 'completed']);
        Task::factory()->create(['title' => 'Keep me']);

        $this->deleteJson("/api/categories/{$category->id}")->assertOk();

        // The defect this covers: the project left the sidebar and its tasks stayed in All tasks,
        // already completed, as rows nobody had created.
        $this->assertSame(['Keep me'], $this->titles());
    }

    public function test_deleting_a_project_leaves_its_tasks_recoverable(): void
    {
        $category = Category::factory()->create();
        $task = Task::factory()->create(['category_id' => $category->id]);

        $this->deleteJson("/api/categories/{$category->id}")->assertOk();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        $this->assertSame(0, $this->getJson('/api/tasks/stats')->json('data.total'));
    }

    public function test_undo_brings_the_project_back_with_its_tasks(): void
    {
        $category = Category::factory()->create();
        Task::factory()->create(['category_id' => $category->id, 'title' => 'Practice']);

        $this->deleteJson("/api/categories/{$category->id}")->assertOk();
        $this->patchJson("/api/categories/{$category->id}/restore")->assertOk();

        $this->assertSame(['Practice'], $this->titles());
    }

    public function test_undo_brings_back_a_task_that_was_already_deleted_by_hand(): void
    {
        $category = Category::factory()->create();
        $gone = Task::factory()->create(['category_id' => $category->id, 'title' => 'Thrown away', 'priority' => 'low']);
        Task::factory()->create(['category_id' => $category->id, 'title' => 'Practice', 'priority' => 'high']);

        $this->deleteJson("/api/tasks/{$gone->id}")->assertOk();
        $this->deleteJson("/api/categories/{$category->id}")->assertOk();
        $this->patchJson("/api/categories/{$category->id}/restore")->assertOk();

        // Undo restores one row too many rather than leaving work destroyed. Telling the two
        // deletes apart would need a column remembering which took which row.
        $this->assertSame(['Practice', 'Thrown away'], $this->titles());
    }

    public function test_a_duplicated_project_that_is_deleted_leaves_no_ghost_tasks(): void
    {
        $category = Category::factory()->create(['name' => 'Basketball']);
        Task::factory()->create(['category_id' => $category->id, 'title' => 'Practice', 'status' => 'completed']);

        $copyId = $this->postJson("/api/categories/{$category->id}/duplicate")->assertCreated()->json('data.id');
        $this->deleteJson("/api/categories/{$copyId}")->assertOk();

        // The copy keeps its status by design, so the ghost it leaves behind reads as Done.
        $this->assertSame(['Practice'], $this->titles());
    }

    public function test_deleting_a_project_for_good_takes_its_tasks_for_good(): void
    {
        $category = Category::factory()->create();
        $task = Task::factory()->create(['category_id' => $category->id]);

        $this->deleteJson("/api/categories/{$category->id}")->assertOk();
        $this->deleteJson("/api/categories/{$category->id}/force")->assertOk();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
}
