<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskProjectNameTest extends TestCase
{
    use RefreshDatabase;

    private function createTask(array $extra = [])
    {
        return $this->postJson('/api/tasks', ['title' => 'A task', 'priority' => 'low', ...$extra]);
    }

    public function test_creates_a_new_project_from_a_name(): void
    {
        $this->createTask(['category_name' => 'Work'])
            ->assertCreated()
            ->assertJsonPath('data.category.name', 'Work');

        $this->assertSame(['Work'], Category::pluck('name')->all());
    }

    public function test_the_category_in_a_response_is_exactly_id_and_name(): void
    {
        $with = $this->createTask(['category_name' => 'Work'])->assertCreated()->json('data.category');
        $without = $this->createTask()->assertCreated()->json('data.category');

        $this->assertSame(['id', 'name'], array_keys($with));
        $this->assertNull($without);
    }

    public function test_reuses_an_existing_project(): void
    {
        $work = Category::factory()->create(['name' => 'Work']);

        $this->createTask(['category_name' => 'Work'])
            ->assertCreated()
            ->assertJsonPath('data.category.id', $work->id);

        $this->assertSame(1, Category::count());
    }

    public function test_reuses_a_project_case_insensitively(): void
    {
        $work = Category::factory()->create(['name' => 'Work']);

        $this->createTask(['category_name' => 'work'])
            ->assertCreated()
            ->assertJsonPath('data.category.id', $work->id);

        $this->assertSame(1, Category::count());
    }

    public function test_trims_the_name(): void
    {
        $this->createTask(['category_name' => '  Home  '])
            ->assertCreated()
            ->assertJsonPath('data.category.name', 'Home');
    }

    public function test_null_empty_and_whitespace_leave_no_project(): void
    {
        foreach ([null, '', '   '] as $name) {
            $this->createTask(['category_name' => $name])
                ->assertCreated()
                ->assertJsonPath('data.category', null);
        }

        $this->assertSame(0, Category::count());
    }

    public function test_rejects_a_name_over_forty_characters(): void
    {
        $this->createTask(['category_name' => str_repeat('a', 41)])
            ->assertStatus(400)
            ->assertJsonValidationErrors('category_name');

        $this->assertSame(0, Task::count());
        $this->assertSame(0, Category::count());
    }

    public function test_accepts_a_name_of_forty_characters(): void
    {
        $this->createTask(['category_name' => str_repeat('a', 40)])->assertCreated();
    }

    public function test_category_id_is_ignored(): void
    {
        $category = Category::factory()->create();

        $this->createTask(['category_id' => $category->id])
            ->assertCreated()
            ->assertJsonPath('data.category', null);
    }

    public function test_patch_sets_changes_and_clears_the_project(): void
    {
        $task = Task::factory()->create();

        $this->patchJson("/api/tasks/{$task->id}", ['category_name' => 'Work'])
            ->assertOk()
            ->assertJsonPath('data.category.name', 'Work');

        $this->patchJson("/api/tasks/{$task->id}", ['category_name' => 'Home'])
            ->assertOk()
            ->assertJsonPath('data.category.name', 'Home');

        $this->patchJson("/api/tasks/{$task->id}", ['category_name' => null])
            ->assertOk()
            ->assertJsonPath('data.category', null);

        $this->patchJson("/api/tasks/{$task->id}", ['category_name' => 'Home'])->assertOk();
        $this->patchJson("/api/tasks/{$task->id}", ['category_name' => '  '])
            ->assertOk()
            ->assertJsonPath('data.category', null);
    }

    public function test_patch_without_the_key_leaves_the_project_alone(): void
    {
        $task = Task::factory()->for(Category::factory()->state(['name' => 'Work']))->create();

        $this->patchJson("/api/tasks/{$task->id}", ['priority' => 'high'])
            ->assertOk()
            ->assertJsonPath('data.category.name', 'Work');
    }

    public function test_patch_rejects_a_name_over_forty_characters(): void
    {
        $task = Task::factory()->create();

        $this->patchJson("/api/tasks/{$task->id}", ['category_name' => str_repeat('a', 41)])
            ->assertStatus(400)
            ->assertJsonValidationErrors('category_name');
    }

    public function test_the_new_project_appears_in_the_project_list_with_its_count(): void
    {
        $this->createTask(['category_name' => 'Work'])->assertCreated();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Work')
            ->assertJsonPath('data.0.task_count', 1);
    }
}
