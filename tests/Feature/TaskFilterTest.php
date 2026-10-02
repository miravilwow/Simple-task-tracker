<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The filter bar on Upcoming, Overdue and Completed narrows by project and by name.
 */
class TaskFilterTest extends TestCase
{
    use RefreshDatabase;

    private function titles(string $query): array
    {
        return array_column($this->getJson("/api/tasks?{$query}")->assertOk()->json('data'), 'title');
    }

    public function test_project_narrows_to_one_project(): void
    {
        $work = Category::create(['name' => 'Work']);
        Task::factory()->create(['title' => 'Ship it', 'category_id' => $work->id]);
        Task::factory()->create(['title' => 'Loose']);

        $this->assertSame(['Ship it'], $this->titles("project={$work->id}"));
    }

    public function test_project_none_finds_tasks_without_a_project(): void
    {
        $work = Category::create(['name' => 'Work']);
        Task::factory()->create(['title' => 'Ship it', 'category_id' => $work->id]);
        Task::factory()->create(['title' => 'Loose']);

        $this->assertSame(['Loose'], $this->titles('project=none'));
    }

    public function test_project_none_combines_with_status(): void
    {
        Task::factory()->create(['title' => 'Open loose']);
        Task::factory()->completed()->create(['title' => 'Done loose']);

        $this->assertSame(['Done loose'], $this->titles('project=none&status=completed'));
    }

    public function test_an_unknown_project_is_a_400(): void
    {
        $this->getJson('/api/tasks?project=999')->assertStatus(400)->assertJsonValidationErrors('project');
        $this->getJson('/api/tasks?project=abc')->assertStatus(400)->assertJsonValidationErrors('project');
    }

    public function test_search_matches_part_of_a_title_in_any_case(): void
    {
        Task::factory()->create(['title' => 'Write the Report']);
        Task::factory()->create(['title' => 'Buy milk']);

        $this->assertSame(['Write the Report'], $this->titles('search=report'));
    }

    public function test_search_treats_percent_and_underscore_literally(): void
    {
        Task::factory()->create(['title' => 'Raise to 5%d done']);
        Task::factory()->create(['title' => 'Anything else']);
        Task::factory()->create(['title' => 'snake_case names']);
        Task::factory()->create(['title' => 'snakeXcase']);
        Task::factory()->create(['title' => '5 and d']);

        $this->assertSame(['Raise to 5%d done'], $this->titles('search='.urlencode('5%d')));
        $this->assertSame(['snake_case names'], $this->titles('search=e_c'));
    }

    public function test_a_search_over_100_characters_is_a_400(): void
    {
        $this->getJson('/api/tasks?search='.str_repeat('a', 101))
            ->assertStatus(400)
            ->assertJsonValidationErrors('search');
    }
}
