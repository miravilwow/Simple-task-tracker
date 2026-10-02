<?php

namespace Database\Seeders;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Category;
use App\Models\Task;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $demo = [
            ['name' => 'Work'],
            ['name' => 'Personal'],
            ['name' => 'School'],
            ['name' => 'Gaming'],
        ];

        // Running this twice used to stop on a duplicate project name. Someone following the README
        // should be able to run it again and have nothing happen, rather than read a stack trace
        // and wonder what they broke.
        if (Category::query()->whereIn('name', array_column($demo, 'name'))->exists()) {
            $this->command?->info('Demo data is already here, so nothing was added.');

            return;
        }

        $categories = collect($demo)->mapWithKeys(fn (array $attributes) => [
            $attributes['name'] => Category::create($attributes),
        ]);

        // [title, description, category, priority, status, created hours ago, due in days, due at]
        // Most rows have no time: a time is for work that happens at an hour, and most work does
        // not, so the demo data has to show both.
        $tasks = [
            ['Fix checkout payment timeout', 'Customers see a 504 after 30 seconds on the payment step.', 'Work', TaskPriority::High, TaskStatus::Pending, 30, -1, null],
            ['Rotate expired API keys', null, 'Work', TaskPriority::High, TaskStatus::Pending, 6, 0, null],
            ['Review pull request #42', null, 'Work', TaskPriority::Medium, TaskStatus::InReview, 3, 0, '14:00'],
            ['Write README setup steps', 'Cover composer, npm, .env, migrations, and tests.', 'Work', TaskPriority::Medium, TaskStatus::InReview, 20, 1, null],
            ['Submit thesis outline', 'Three chapters plus the bibliography.', 'School', TaskPriority::High, TaskStatus::Pending, 50, 3, '23:59'],
            ['Read chapter 7 notes', null, 'School', TaskPriority::Low, TaskStatus::Pending, 26, 5, null],
            ['Book dentist appointment', null, 'Personal', TaskPriority::Medium, TaskStatus::Pending, 14, 2, '09:30'],
            ['Renew gym membership', null, 'Personal', TaskPriority::Low, TaskStatus::Pending, 60, 7, null],
            ['Finish the co-op campaign', 'Two chapters left before the season ends.', 'Gaming', TaskPriority::Low, TaskStatus::Pending, 8, 6, '20:00'],
            ['Set up Git repository', 'Initial commit and branch protection on main.', 'Work', TaskPriority::High, TaskStatus::Completed, 48, -2, null],
            ['Design task list empty state', null, 'Work', TaskPriority::Medium, TaskStatus::Completed, 40, -2, null],
            ['Clean up unused CSS classes', null, 'Work', TaskPriority::Low, TaskStatus::Completed, 36, null, null],
        ];

        foreach ($tasks as [$title, $description, $category, $priority, $status, $hoursAgo, $dueInDays, $dueAt]) {
            $createdAt = now()->subHours($hoursAgo);

            Task::factory()->create([
                'title' => $title,
                'description' => $description,
                'category_id' => $categories[$category]->id,
                'priority' => $priority,
                'status' => $status,
                'due_date' => $dueInDays === null ? null : today()->addDays($dueInDays),
                'due_time' => $dueAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
