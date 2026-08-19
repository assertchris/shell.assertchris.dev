<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Resources\AccountResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateAccount extends CreateRecord
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
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('mail_used')),
                    ]),
                Section::make('Google Calendar')
                    ->schema([
                        Toggle::make('google_calendar_used')
                            ->default(false)
                            ->live(),
                    ]),
                Section::make('Slack')
                    ->schema([
                        Toggle::make('slack_used')
                            ->default(false)
                            ->live(),
                    ]),
                Section::make('GitHub')
                    ->schema([
                        Toggle::make('github_used')
                            ->default(false)
                            ->live(),
                        TextInput::make('github_pat')
                            ->password()
                            ->revealable()
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('github_used'))
                            ->requiredIf('github_used', true),
                    ]),
            ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data['user_id'] = auth()->id();

        return static::getModel()::create($data);
    }
}
