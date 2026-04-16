<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Account extends Model
{
    /** @use HasFactory<\Database\Factories\AccountFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'mail_used',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
        'google_calendar_used',
        'google_calendar_access_token',
        'google_calendar_refresh_token',
        'google_calendar_token_expires_at',
        'slack_used',
        'slack_access_token',
        'slack_team_id',
        'slack_team_name',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mail_password' => 'encrypted',
            'google_calendar_access_token' => 'encrypted',
            'google_calendar_refresh_token' => 'encrypted',
            'google_calendar_token_expires_at' => 'datetime',
            'slack_access_token' => 'encrypted',
            'mail_used' => 'boolean',
            'google_calendar_used' => 'boolean',
            'slack_used' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isGoogleCalendarConnected(): bool
    {
        return $this->google_calendar_access_token !== null && $this->google_calendar_used;
    }

    public function isSlackConnected(): bool
    {
        return $this->slack_used && ! empty($this->slack_access_token);
    }

    /**
     * @return array{host: string, port: int, encryption: string, username: string, password: string}
     */
    public function toImapConfig(): array
    {
        return [
            'host' => $this->mail_host,
            'port' => $this->mail_port,
            'encryption' => $this->mail_encryption,
            'username' => $this->mail_username,
            'password' => $this->mail_password,
        ];
    }
}
