<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $guarded = [];

    protected $casts = ['due_date' => 'date', 'done_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->whereNull('done_at');
    }

    public function isOverdue(): bool
    {
        return ! $this->done_at && $this->due_date && $this->due_date->lt(now('Europe/Madrid')->startOfDay());
    }

    public function isToday(): bool
    {
        return ! $this->done_at && $this->due_date && $this->due_date->isSameDay(now('Europe/Madrid'));
    }

    /** @return array{pending:int,overdue:int,today:int} */
    public static function counters(): array
    {
        $today = now('Europe/Madrid')->toDateString();

        return [
            'pending' => static::pending()->count(),
            'overdue' => static::pending()->whereDate('due_date', '<', $today)->count(),
            'today' => static::pending()->whereDate('due_date', $today)->count(),
        ];
    }
}
