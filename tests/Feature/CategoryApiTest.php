<?php

namespace Tests\Feature;

use App\Enums\CategoryIcon;
use App\Models\Category;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_categories_alphabetically_with_task_counts(): void
    {
        $work = Category::factory()->create(['name' => 'Work']);
        Category::factory()->create(['name' => 'Gaming']);
        Task::factory()->count(2)->create(['category_id' => $work->id]);

        $response = $this->getJson('/api/categories');

        $response->assertOk();
        $this->assertSame(['Gaming', 'Work'], array_column($response->json('data'), 'name'));
        $this->assertSame([0, 2], array_column($response->json('data'), 'task_count'));
    }

    public function test_creates_a_category(): void
    {
        $this->postJson('/api/categories', ['name' => 'Errands', 'color' => 'green', 'icon' => 'folder'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Errands')
            ->assertJsonPath('data.color', 'green')
            ->assertJsonPath('data.task_count', 0);

        $this->assertDatabaseHas('categories', ['name' => 'Errands']);
    }

    public function test_rejects_a_duplicate_category_name(): void
    {
        Category::factory()->create(['name' => 'Work']);

        $this->postJson('/api/categories', ['name' => 'Work', 'color' => 'blue', 'icon' => 'briefcase'])
            ->assertStatus(400)
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('categories', 1);
    }

    public function test_creates_a_category_with_an_icon(): void
    {
        $this->postJson('/api/categories', ['name' => 'Fitness', 'color' => 'green', 'icon' => 'dumbbell'])
            ->assertCreated()
            ->assertJsonPath('data.icon', 'dumbbell')
            ->assertJsonPath('data.color', 'green');

        $this->assertDatabaseHas('categories', ['name' => 'Fitness', 'icon' => 'dumbbell']);
    }

    public function test_rejects_an_unknown_icon(): void
    {
        $this->postJson('/api/categories', ['name' => 'Nope', 'color' => 'red', 'icon' => 'rocket-ship'])
            ->assertStatus(400)
            ->assertJsonValidationErrors('icon');
    }

    public function test_every_icon_the_enum_offers_is_safelisted_in_the_stylesheet(): void
    {
        // The class is built at runtime, so Tailwind only emits it because app.css lists it.
        // A case added to the enum without the safelist renders as an empty box in the sidebar.
        $css = file_get_contents(base_path('resources/css/app.css'));

        foreach (CategoryIcon::cases() as $icon) {
            $this->assertStringContainsString(
                $icon->value.',',
                str_replace('}', ',', $css),
                "CategoryIcon::{$icon->name} is missing from the @source inline list in app.css"
            );
        }
    }

    public function test_rejects_an_invalid_colour(): void
    {
        $this->postJson('/api/categories', ['name' => 'Errands', 'color' => 'turquoise', 'icon' => 'folder'])
            ->assertStatus(400)
            ->assertJsonValidationErrors('color');
    }

    public function test_deleting_a_category_keeps_its_tasks_but_clears_the_link(): void
    {
        $category = Category::factory()->create();
        $task = Task::factory()->create(['category_id' => $category->id]);

        $this->deleteJson("/api/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Category deleted.');

        $this->assertModelMissing($category);
        $this->assertNull($task->fresh()->category_id);
    }

    public function test_delete_returns_404_for_missing_category(): void
    {
        $this->deleteJson('/api/categories/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Category not found.']);
    }
}
