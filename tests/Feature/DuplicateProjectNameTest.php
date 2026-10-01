<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The name a copy picks has to be free in the same set the database checks, which includes the
 * deleted projects: the unique index spans them, because a deleted project keeps its name
 * reserved while it can still be restored.
 */
class DuplicateProjectNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicating_works_when_a_deleted_project_holds_the_copy_name(): void
    {
        $category = Category::factory()->create(['name' => 'Work']);
        Category::factory()->create(['name' => 'Work (copy)'])->delete();

        // This was a database error before: the search looked only at the visible projects, so it
        // offered a name the index had already reserved for the deleted one.
        $this->postJson("/api/categories/{$category->id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.name', 'Work (copy 2)');
    }

    public function test_duplicating_skips_every_name_already_taken(): void
    {
        $category = Category::factory()->create(['name' => 'Work']);
        Category::factory()->create(['name' => 'Work (copy)']);
        Category::factory()->create(['name' => 'Work (copy 2)'])->delete();
        Category::factory()->create(['name' => 'Work (copy 3)']);

        $this->postJson("/api/categories/{$category->id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.name', 'Work (copy 4)');
    }

    public function test_finding_a_free_name_takes_one_query_however_many_copies_exist(): void
    {
        $category = Category::factory()->create(['name' => 'Work']);
        Category::factory()->create(['name' => 'Work (copy)']);

        foreach (range(2, 12) as $suffix) {
            Category::factory()->create(['name' => "Work (copy {$suffix})"]);
        }

        DB::enableQueryLog();
        $this->postJson("/api/categories/{$category->id}/duplicate")->assertCreated();

        $lookups = array_filter(
            DB::getQueryLog(),
            fn (array $entry) => str_contains($entry['query'], 'like'),
        );

        // One query for every taken name, not one query per attempt. Twelve copies used to mean
        // twelve round trips to find the thirteenth.
        $this->assertCount(1, $lookups);
    }

    public function test_a_long_name_still_fits_the_column(): void
    {
        $category = Category::factory()->create(['name' => str_repeat('a', 40)]);

        $name = $this->postJson("/api/categories/{$category->id}/duplicate")
            ->assertCreated()
            ->json('data.name');

        $this->assertLessThanOrEqual(40, mb_strlen($name));
        $this->assertStringEndsWith(' (copy)', $name);
    }
}
