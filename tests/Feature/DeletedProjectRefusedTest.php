<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A deleted project is one nobody can see, so nothing may be pointed at it.
 *
 * The exists rules used to accept its id: a task could be created in a project that had been
 * thrown away, and the list could be filtered by one. The reply was a quiet 200 with nothing in
 * it, which is the same silent no-op the rest of the API refuses on principle.
 */
class DeletedProjectRefusedTest extends TestCase
{
    use RefreshDatabase;

    private function deletedProject(): Category
    {
        $category = Category::factory()->create(['name' => 'Thrown away']);
        $category->delete();

        return $category;
    }

    public function test_a_task_cannot_be_created_in_a_deleted_project(): void
    {
        $this->postJson('/api/tasks', [
            'title' => 'Nowhere to go',
            'priority' => 'low',
            'category_id' => $this->deletedProject()->id,
        ])
            ->assertStatus(400)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_a_task_cannot_be_moved_into_a_deleted_project(): void
    {
        $task = Task::factory()->create();

        $this->patchJson("/api/tasks/{$task->id}", ['category_id' => $this->deletedProject()->id])
            ->assertStatus(400)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_the_list_cannot_be_filtered_by_a_deleted_project(): void
    {
        $this->getJson("/api/tasks?category_id={$this->deletedProject()->id}")
            ->assertStatus(400)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_a_project_cannot_be_moved_under_a_deleted_one(): void
    {
        $category = Category::factory()->create(['name' => 'Still here']);

        $this->patchJson("/api/categories/{$category->id}/move", ['parent_id' => $this->deletedProject()->id])
            ->assertStatus(400)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_a_live_project_is_still_accepted(): void
    {
        $category = Category::factory()->create(['name' => 'Work']);

        $this->postJson('/api/tasks', ['title' => 'Real work', 'priority' => 'low', 'category_id' => $category->id])
            ->assertCreated()
            ->assertJsonPath('data.category.id', $category->id);
    }
}
