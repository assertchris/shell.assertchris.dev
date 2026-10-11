<?php

namespace App\Models;

use AC\LaravelTaggedCache\TaggedCache;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'mail_used',
        'mail_read',
        'mail_write',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
        'google_calendar_used',
        'google_calendar_read',
        'google_calendar_write',
        'google_calendar_access_token',
        'google_calendar_refresh_token',
        'google_calendar_token_expires_at',
        'slack_used',
        'slack_read',
        'slack_write',
        'slack_access_token',
        'slack_team_id',
        'slack_team_name',
        'github_used',
        'github_pat',
        'jira_used',
        'jira_base_url',
        'jira_email',
        'jira_api_token',
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
            'mail_read' => 'boolean',
            'mail_write' => 'boolean',
            'google_calendar_used' => 'boolean',
            'google_calendar_read' => 'boolean',
            'google_calendar_write' => 'boolean',
            'slack_used' => 'boolean',
            'slack_read' => 'boolean',
            'slack_write' => 'boolean',
            'github_used' => 'boolean',
            'github_pat' => 'encrypted',
            'jira_used' => 'boolean',
            'jira_api_token' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function resolveRouteBinding($value, $field = null): ?static
    {
        return TaggedCache::for(tags: ['accounts'], context: ['id' => (int) $value])
            ->remember(config('cache.ttl.models'), fn () => static::find($value));
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

    public function isGitHubConnected(): bool
    {
        return $this->github_used && ! empty($this->github_pat);
    }

    public function isJiraConnected(): bool
    {
        return $this->jira_used
            && ! empty($this->jira_base_url)
            && ! empty($this->jira_email)
            && ! empty($this->jira_api_token);
    }

    /**
     * @return array{pat: string}
     */
    public function toGitHubConfig(): array
    {
        return ['pat' => $this->github_pat];
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
