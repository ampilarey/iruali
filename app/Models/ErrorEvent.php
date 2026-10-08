<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per distinct exception (class + file + line), counted up each time it happens.
 */
class ErrorEvent extends Model
{
    protected $fillable = [
        'fingerprint', 'exception_class', 'message', 'file', 'line', 'url', 'user_id',
        'count', 'first_seen_at', 'last_seen_at', 'resolved_at',
    ];

    protected $casts = [
        'count' => 'integer',
        'line' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function shortClass(): string
    {
        return class_basename($this->exception_class);
    }

    /** The file path relative to the app root, so the list stays readable. */
    public function shortFile(): string
    {
        return ltrim(str_replace(base_path(), '', (string) $this->file), '/');
    }
}
