<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The actions behind a project's overflow menu: favourite, archive, move, duplicate.
 */
class ProjectMenuTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------ favourites

    public function test_marks_a_project_as_a_favourite_and_clears_it_again(): void
    {
        $category = Category::factory()->create();

        $this->patchJson("/api/categories/{$category->id}/favorite", ['is_favorite' => true])
            ->assertOk()
            ->assertJsonPath('data.is_favorite', true);

        $this->patchJson("/api/categories/{$category->id}/favorite", ['is_favorite' => false])
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false);
    }

    public function test_favourite_requires_the_value_it_is_setting(): void
    {
        // Idempotent rather than a toggle, so the key is not optional.
        $category = Category::factory()->create();

        $this->patchJson("/api/categories/{$category->id}/favorite", [])
            ->assertStatus(400)
            ->assertJsonValidationErrors('is_favorite');
    }

    // ------------------------------------------------------------ archiving

    public function test_archiving_hides_a_project_from_the_list_without_touching_its_tasks(): void
    {
        $category = Category::factory()->create();
        $task = Task::factory()->create(['category_id' => $category->id]);

        $this->patchJson("/api/categories/{$category->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.is_archived', true);

        $this->assertSame([], $this->getJson('/api/categories')->json('data'));
        $this->assertSame($category->id, $task->fresh()->category_id);
    }

    public function test_archived_projects_are_listed_when_asked_for(): void
    {
        $archived = Category::factory()->create(['name' => 'Old work']);
        Category::factory()->create(['name' => 'Current']);
        $this->patchJson("/api/categories/{$archived->id}/archive")->assertOk();

        $names = array_column($this->getJson('/api/categories?archived=1')->json('data'), 'name');

        $this->assertSame(['Old work'], $names);
    }

    public function test_unarchiving_brings_a_project_back(): void
    {
        $category = Category::factory()->create();
        $this->patchJson("/api/categories/{$category->id}/archive")->assertOk();

        $this->patchJson("/api/categories/{$category->id}/unarchive")
            ->assertOk()
            ->assertJsonPath('data.is_archived', false);

        $this->assertCount(1, $this->getJson('/api/categories')->json('data'));
    }

    // ------------------------------------------------------------ the tree

    public function test_moves_a_project_under_another_and_back_to_the_top_level(): void
    {
        $parent = Category::factory()->create(['name' => 'Work']);
        $child = Category::factory()->create(['name' => 'Reports']);

        $this->patchJson("/api/categories/{$child->id}/move", ['parent_id' => $parent->id])
            ->assertOk()
            ->assertJsonPath('data.parent_id', $parent->id);

        $this->patchJson("/api/categories/{$child->id}/move", ['parent_id' => null])
            ->assertOk()
            ->assertJsonPath('data.parent_id', null);
    }

    public function test_a_project_cannot_be_moved_inside_itself(): void
    {
        $category = Category::factory()->create();

        $this->patchJson("/api/categories/{$category->id}/move", ['parent_id' => $category->id])
            ->assertStatus(400)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_a_project_cannot_be_moved_under_its_own_child(): void
    {
        // Allowing it would cut the branch off the tree: every project in it would still have a
        // parent, and none of them would be reachable from the top level.
        $parent = Category::factory()->create(['name' => 'Work']);
        $child = Category::factory()->create(['name' => 'Reports', 'parent_id' => $parent->id]);
        $grandchild = Category::factory()->create(['name' => 'Q4', 'parent_id' => $child->id]);

        $this->patchJson("/api/categories/{$parent->id}/move", ['parent_id' => $grandchild->id])
            ->assertStatus(400)
            ->assertJsonValidationErrors('parent_id');

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_move_requires_the_key_so_it_is_never_a_silent_no_op(): void
    {
        $category = Category::factory()->create();

        $this->patchJson("/api/categories/{$category->id}/move", [])
            ->assertStatus(400)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_deleting_a_parent_promotes_its_children(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->deleteJson("/api/categories/{$parent->id}")->assertOk();

        $this->assertNull($child->fresh()->parent_id);
    }

    // ------------------------------------------------------------ duplicating

    public function test_duplicating_copies_the_project_and_its_tasks_as_they_are(): void
    {
        $category = Category::factory()->create(['name' => 'Work', 'icon' => 'briefcase', 'color' => 'violet']);
        Task::factory()->create(['category_id' => $category->id, 'title' => 'Ship it', 'status' => 'completed']);
        Task::factory()->create(['category_id' => $category->id, 'title' => 'Write it', 'status' => 'pending']);

        $response = $this->postJson("/api/categories/{$category->id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.name', 'Work (copy)')
            ->assertJsonPath('data.icon', 'briefcase')
            ->assertJsonPath('data.color', 'violet')
            ->assertJsonPath('data.task_count', 2);

        $copied = Task::where('category_id', $response->json('data.id'))->pluck('status', 'title');

        // A copy that quietly reopened finished work would be a different project, not a copy.
        $this->assertSame('completed', $copied['Ship it']->value);
        $this->assertSame('pending', $copied['Write it']->value);
    }

    public function test_duplicating_twice_finds_a_free_name(): void
    {
        $category = Category::factory()->create(['name' => 'Work']);

        $this->postJson("/api/categories/{$category->id}/duplicate")->assertCreated();
        $this->postJson("/api/categories/{$category->id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.name', 'Work (copy 2)');
    }

    public function test_a_copy_of_a_long_name_still_fits_the_column(): void
    {
        // The suffix grows with the count, so the base has to be trimmed to fit whatever suffix
        // it ends up with. Otherwise the copy overflows and fails as a database error.
        $category = Category::factory()->create(['name' => str_repeat('a', 40)]);

        for ($made = 0; $made < 12; $made++) {
            $name = $this->postJson("/api/categories/{$category->id}/duplicate")
                ->assertCreated()
                ->json('data.name');

            $this->assertLessThanOrEqual(40, mb_strlen($name), "\"{$name}\" is longer than the column");
        }
    }

    public function test_a_copy_is_top_level_and_not_a_favourite(): void
    {
        $parent = Category::factory()->create(['name' => 'Work']);
        $category = Category::factory()->create([
            'name' => 'Reports',
            'parent_id' => $parent->id,
            'is_favorite' => true,
        ]);

        $this->postJson("/api/categories/{$category->id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.parent_id', null)
            ->assertJsonPath('data.is_favorite', false);
    }

    // ------------------------------------------------------------ 404s

    public function test_every_project_action_is_404_for_a_missing_project(): void
    {
        $this->patchJson('/api/categories/999/favorite', ['is_favorite' => true])->assertNotFound();
        $this->patchJson('/api/categories/999/archive')->assertNotFound();
        $this->patchJson('/api/categories/999/unarchive')->assertNotFound();
        $this->patchJson('/api/categories/999/move', ['parent_id' => null])->assertNotFound();
        $this->postJson('/api/categories/999/duplicate')->assertNotFound();
    }
}
