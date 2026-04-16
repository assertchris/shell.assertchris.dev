<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Resources\AccountResource;
use App\Models\Account;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Section::make('Mail')
                    ->schema([
                        Toggle::make('mail_used')
                            ->default(true)
                            ->live(),
                        TextInput::make('mail_host')
                            ->maxLength(255)
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('mail_used')),
                        TextInput::make('mail_port')
                            ->numeric()
                            ->default(993)
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('mail_used')),
                        Select::make('mail_encryption')
                            ->options([
                                'ssl' => 'SSL',
                                'tls' => 'TLS',
                                'none' => 'None',
                            ])
                            ->default('ssl')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('mail_used')),
                        TextInput::make('mail_username')
                            ->maxLength(255)
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('mail_used')),
                        TextInput::make('mail_password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn (?string $state, $record) => filled($state) ? $state : $record->mail_password)
                            ->helperText('Leave blank to keep the current password.')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('mail_used')),
                    ]),
                Section::make('Google Calendar')
                    ->schema([
                        Toggle::make('google_calendar_used')
                            ->default(false)
                            ->live(),
                        Action::make('connectGoogle')
                            ->label('Connect Google Account')
                            ->url(fn (Account $record): string => route('google.redirect', $record))
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get, Account $record): bool => $get('google_calendar_used') && ! $record->isGoogleCalendarConnected()),
                        Action::make('disconnectGoogle')
                            ->label('Disconnect Google')
                            ->requiresConfirmation()
                            ->action(function (Account $record): void {
                                $record->update([
                                    'google_calendar_access_token' => null,
                                    'google_calendar_refresh_token' => null,
                                    'google_calendar_token_expires_at' => null,
                                    'google_calendar_used' => false,
                                ]);
                                Notification::make()->title('Google Calendar disconnected.')->success()->send();
                            })
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get, Account $record): bool => $get('google_calendar_used') && $record->isGoogleCalendarConnected()),
                    ]),
                Section::make('Slack')
                    ->schema([
                        Toggle::make('slack_used')
                            ->default(false)
                            ->live(),
                        Action::make('connectSlack')
                            ->label('Connect Slack')
                            ->url(fn (Account $record): string => route('slack.redirect', $record))
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get, Account $record): bool => $get('slack_used') && ! $record->isSlackConnected()),
                        Action::make('disconnectSlack')
                            ->label('Disconnect Slack')
                            ->requiresConfirmation()
                            ->action(function (Account $record): void {
                                $record->update([
                                    'slack_access_token' => null,
                                    'slack_team_id' => null,
                                    'slack_team_name' => null,
                                    'slack_used' => false,
                                ]);
                                Notification::make()->title('Slack disconnected.')->success()->send();
                            })
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get, Account $record): bool => $get('slack_used') && $record->isSlackConnected()),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
