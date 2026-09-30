<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    public const PRIORITIES = ['low', 'medium', 'high'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_COMPLETED];

    protected $fillable = [
        'title',
        'description',
        'priority',
    ];

    // Mirrors the DB defaults so a freshly created model includes them in its JSON.
    protected $attributes = [
        'priority' => 'medium',
        'status' => self::STATUS_PENDING,
    ];
}
