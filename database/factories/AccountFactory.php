<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'mail_used' => true,
            'mail_host' => 'imap.'.fake()->domainName(),
            'mail_port' => 993,
            'mail_encryption' => 'ssl',
            'mail_username' => fake()->safeEmail(),
            'mail_password' => fake()->password(16),
            'google_calendar_used' => false,
            'google_calendar_access_token' => null,
            'google_calendar_refresh_token' => null,
            'google_calendar_token_expires_at' => null,
            'slack_used' => false,
            'slack_access_token' => null,
            'slack_team_id' => null,
            'slack_team_name' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withGoogleCalendar(): static
    {
        return $this->state(fn (array $attributes) => [
            'google_calendar_used' => true,
            'google_calendar_read' => true,
            'google_calendar_write' => true,
            'google_calendar_access_token' => fake()->sha256(),
            'google_calendar_refresh_token' => fake()->sha256(),
            'google_calendar_token_expires_at' => now()->addHour(),
        ]);
    }

    public function withCalendarReadOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'google_calendar_read' => true,
            'google_calendar_write' => false,
        ]);
    }

    public function mailOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'mail_read' => true,
            'mail_write' => true,
            'google_calendar_used' => false,
            'google_calendar_access_token' => null,
            'google_calendar_refresh_token' => null,
            'google_calendar_token_expires_at' => null,
        ]);
    }

    public function withSlack(): static
    {
        return $this->state(fn (array $attributes) => [
            'slack_used' => true,
            'slack_read' => true,
            'slack_write' => true,
            'slack_access_token' => fake()->sha256(),
            'slack_team_id' => 'T'.fake()->bothify('??########'),
            'slack_team_name' => fake()->company(),
        ]);
    }

    public function withSlackReadOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'slack_read' => true,
            'slack_write' => false,
        ]);
    }

    public function withGitHub(): static
    {
        return $this->state(fn (array $attributes) => [
            'github_used' => true,
            'github_pat' => fake()->sha256(),
        ]);
    }
}
