<?php

namespace App\Models;

use App\Enums\DashboardMessagePriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardMessage extends Model
{
    /** @use HasFactory<\Database\Factories\DashboardMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'priority',
        'source',
        'source_token_id',
        'read_at',
        'archived_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => DashboardMessagePriority::class,
            'read_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
