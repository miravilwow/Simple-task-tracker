<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The request counter, and where it is kept.
 *
 * It used to live in the default cache store, which is the database: one call to the task list
 * cost twelve database trips, nine of them the counter rather than the data.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_request_counter_is_not_kept_in_the_database(): void
    {
        // Laravel hands the limiter the default store when cache.limiter is unset, and the default
        // here is the database. The counter has no business sitting beside the tasks.
        $this->assertNotSame('database', config('cache.limiter'));
    }

    public function test_reading_the_task_list_costs_two_database_queries(): void
    {
        // Three tasks across two projects, because the second query only happens when there is a
        // project to fetch: an empty table would pass this test without proving anything.
        $projects = Category::factory()->count(2)->create();
        Task::factory()->create(['category_id' => $projects[0]->id]);
        Task::factory()->create(['category_id' => $projects[1]->id]);
        Task::factory()->create(['category_id' => $projects[0]->id]);

        DB::enableQueryLog();

        $this->getJson('/api/tasks')->assertOk();

        // The tasks, then every project they belong to in one go. Nothing else: no query per row,
        // and nothing that is only counting requests.
        $this->assertCount(2, DB::getQueryLog());
    }

    public function test_the_counter_still_counts_between_requests(): void
    {
        // A store that forgets between requests would leave the limit never limiting anything,
        // which is the trap in reaching for an in-memory store to make this cheap.
        $first = $this->getJson('/api/tasks/stats')->assertOk()->headers->get('X-RateLimit-Remaining');
        $second = $this->getJson('/api/tasks/stats')->assertOk()->headers->get('X-RateLimit-Remaining');

        $this->assertSame((int) $first - 1, (int) $second);
    }

    public function test_going_over_the_limit_answers_429(): void
    {
        // Two per minute for this test only, so the refusal is proven without three hundred calls.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(2)->by('over-the-limit'));

        $this->getJson('/api/tasks/stats')->assertOk();
        $this->getJson('/api/tasks/stats')->assertOk();
        $this->getJson('/api/tasks/stats')->assertStatus(429);
    }

    public function test_the_published_ceiling_is_the_one_the_documentation_names(): void
    {
        $this->getJson('/api/tasks/stats')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', 300);
    }
}
