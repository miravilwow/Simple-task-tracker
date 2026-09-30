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
        $this->postJson('/api/categories', ['name' => 'Errands', 'icon' => 'folder'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Errands')
            ->assertJsonPath('data.icon', 'folder')
            ->assertJsonPath('data.task_count', 0);

        $this->assertDatabaseHas('categories', ['name' => 'Errands']);
    }

    public function test_rejects_a_duplicate_category_name(): void
    {
        Category::factory()->create(['name' => 'Work']);

        $this->postJson('/api/categories', ['name' => 'Work', 'icon' => 'briefcase'])
            ->assertStatus(400)
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('categories', 1);
    }

    public function test_creates_a_category_with_an_icon(): void
    {
        $this->postJson('/api/categories', ['name' => 'Fitness', 'icon' => 'dumbbell'])
            ->assertCreated()
            ->assertJsonPath('data.icon', 'dumbbell');

        $this->assertDatabaseHas('categories', ['name' => 'Fitness', 'icon' => 'dumbbell']);
    }

    public function test_rejects_an_unknown_icon(): void
    {
        $this->postJson('/api/categories', ['name' => 'Nope', 'icon' => 'rocket-ship'])
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

    public function test_every_icon_alias_in_the_picker_points_at_a_real_icon(): void
    {
        // The picker maps the words people type ("gym") onto the set's own names ("dumbbell").
        // A typo there is invisible: the suggestion simply never appears.
        $js = file_get_contents(base_path('resources/js/app.js'));

        preg_match('/const ICON_ALIASES = \{(.*?)\n\};/s', $js, $block);
        $this->assertNotEmpty($block, 'ICON_ALIASES was not found in app.js');

        preg_match_all("/'([a-z0-9-]+)'/", $block[1], $targets);
        $this->assertNotEmpty($targets[1]);

        foreach (array_unique($targets[1]) as $target) {
            $this->assertNotNull(
                CategoryIcon::tryFrom($target),
                "ICON_ALIASES points at \"{$target}\", which is not a CategoryIcon"
            );
        }
    }
    public function test_requires_an_icon(): void
    {
        $this->postJson('/api/categories', ['name' => 'Errands'])
            ->assertStatus(400)
            ->assertJsonValidationErrors('icon');
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

    public function test_renames_a_category_and_changes_its_icon(): void
    {
        $category = Category::factory()->create(['name' => 'Work', 'icon' => 'folder']);

        $this->patchJson("/api/categories/{$category->id}", ['name' => 'Day job', 'icon' => 'briefcase'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Day job')
            ->assertJsonPath('data.icon', 'briefcase')
            ->assertJsonPath('data.task_count', 0);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Day job', 'icon' => 'briefcase']);
    }

    public function test_a_category_may_keep_its_own_name_while_being_updated(): void
    {
        // The unique rule has to ignore the row being edited, or changing only the icon is a 400.
        $category = Category::factory()->create(['name' => 'Work', 'icon' => 'folder']);

        $this->patchJson("/api/categories/{$category->id}", ['name' => 'Work', 'icon' => 'briefcase'])
            ->assertOk()
            ->assertJsonPath('data.icon', 'briefcase');
    }

    public function test_rejects_renaming_a_category_onto_another_name(): void
    {
        Category::factory()->create(['name' => 'Gaming']);
        $category = Category::factory()->create(['name' => 'Work']);

        $this->patchJson("/api/categories/{$category->id}", ['name' => 'Gaming', 'icon' => 'folder'])
            ->assertStatus(400)
            ->assertJsonValidationErrors('name');

        $this->assertSame('Work', $category->fresh()->name);
    }

    public function test_rejects_an_unknown_icon_on_update(): void
    {
        $category = Category::factory()->create();

        $this->patchJson("/api/categories/{$category->id}", ['name' => 'Work', 'icon' => 'rocket-ship'])
            ->assertStatus(400)
            ->assertJsonValidationErrors('icon');
    }

    public function test_update_returns_404_for_missing_category(): void
    {
        $this->patchJson('/api/categories/999', ['name' => 'Work', 'icon' => 'folder'])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Category not found.']);
    }
}
