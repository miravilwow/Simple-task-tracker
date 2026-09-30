<?php

namespace Database\Seeders;

use App\Enums\CategoryColor;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Category;
use App\Models\Task;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            ['name' => 'Work', 'color' => CategoryColor::Blue],
            ['name' => 'Personal', 'color' => CategoryColor::Green],
            ['name' => 'School', 'color' => CategoryColor::Violet],
            ['name' => 'Gaming', 'color' => CategoryColor::Pink],
        ])->mapWithKeys(fn (array $attributes) => [
            $attributes['name'] => Category::create($attributes),
        ]);

        // [title, description, category, priority, status, created hours ago, due in days]
        $tasks = [
            ['Fix checkout payment timeout', 'Customers see a 504 after 30 seconds on the payment step.', 'Work', TaskPriority::High, TaskStatus::Pending, 30, -1],
            ['Rotate expired API keys', null, 'Work', TaskPriority::High, TaskStatus::Pending, 6, 0],
            ['Review pull request #42', null, 'Work', TaskPriority::Medium, TaskStatus::Pending, 3, 0],
            ['Write README setup steps', 'Cover composer, npm, .env, migrations, and tests.', 'Work', TaskPriority::Medium, TaskStatus::Pending, 20, 1],
            ['Submit thesis outline', 'Three chapters plus the bibliography.', 'School', TaskPriority::High, TaskStatus::Pending, 50, 3],
            ['Read chapter 7 notes', null, 'School', TaskPriority::Low, TaskStatus::Pending, 26, 5],
            ['Book dentist appointment', null, 'Personal', TaskPriority::Medium, TaskStatus::Pending, 14, 2],
            ['Renew gym membership', null, 'Personal', TaskPriority::Low, TaskStatus::Pending, 60, 7],
            ['Finish the co-op campaign', 'Two chapters left before the season ends.', 'Gaming', TaskPriority::Low, TaskStatus::Pending, 8, 6],
            ['Set up Git repository', 'Initial commit and branch protection on main.', 'Work', TaskPriority::High, TaskStatus::Completed, 48, -2],
            ['Design task list empty state', null, 'Work', TaskPriority::Medium, TaskStatus::Completed, 40, -2],
            ['Clean up unused CSS classes', null, 'Work', TaskPriority::Low, TaskStatus::Completed, 36, null],
        ];

        foreach ($tasks as [$title, $description, $category, $priority, $status, $hoursAgo, $dueInDays]) {
            $createdAt = now()->subHours($hoursAgo);

            Task::factory()->create([
                'title' => $title,
                'description' => $description,
                'category_id' => $categories[$category]->id,
                'priority' => $priority,
                'status' => $status,
                'due_date' => $dueInDays === null ? null : today()->addDays($dueInDays),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
