<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Filament\Resources\AccountResource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
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
                            ->visible(fn (\Filament\Forms\Get $get) => $get('mail_used')),
                        TextInput::make('mail_port')
                            ->numeric()
                            ->default(993)
                            ->visible(fn (\Filament\Forms\Get $get) => $get('mail_used')),
                        Select::make('mail_encryption')
                            ->options([
                                'ssl' => 'SSL',
                                'tls' => 'TLS',
                                'none' => 'None',
                            ])
                            ->default('ssl')
                            ->visible(fn (\Filament\Forms\Get $get) => $get('mail_used')),
                        TextInput::make('mail_username')
                            ->maxLength(255)
                            ->visible(fn (\Filament\Forms\Get $get) => $get('mail_used')),
                        TextInput::make('mail_password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn (?string $state, $record) => filled($state) ? $state : $record->mail_password)
                            ->helperText('Leave blank to keep the current password.')
                            ->visible(fn (\Filament\Forms\Get $get) => $get('mail_used')),
                    ]),
                Section::make('Google Calendar')
                    ->schema([
                        Toggle::make('google_calendar_used')
                            ->default(false)
                            ->live(),
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
