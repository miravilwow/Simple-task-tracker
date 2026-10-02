<?php

namespace Tests\Feature;

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
        $gaming = Category::factory()->create(['name' => 'Gaming']);
        Task::factory()->count(2)->create(['category_id' => $work->id]);
        Task::factory()->create(['category_id' => $gaming->id]);

        $response = $this->getJson('/api/categories');

        $response->assertOk();
        $this->assertSame(['Gaming', 'Work'], array_column($response->json('data'), 'name'));
        $this->assertSame([1, 2], array_column($response->json('data'), 'task_count'));
        $this->assertSame(['id', 'name', 'task_count'], array_keys($response->json('data.0')));
    }

    public function test_leaves_out_a_category_with_no_live_tasks(): void
    {
        $empty = Category::factory()->create(['name' => 'Empty']);
        $abandoned = Category::factory()->create(['name' => 'Abandoned']);
        Task::factory()->create(['category_id' => $abandoned->id])->delete();

        $response = $this->getJson('/api/categories')->assertOk();

        $this->assertSame([], $response->json('data'));
        $this->assertDatabaseHas('categories', ['id' => $empty->id]);
    }
}
