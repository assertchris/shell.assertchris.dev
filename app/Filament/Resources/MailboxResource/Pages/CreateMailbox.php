<?php

namespace App\Filament\Resources\MailboxResource\Pages;

use App\Filament\Resources\MailboxResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateMailbox extends CreateRecord
{
    protected static string $resource = MailboxResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('host')
                    ->required()
                    ->maxLength(255),
                TextInput::make('port')
                    ->required()
                    ->numeric()
                    ->default(993),
                Select::make('encryption')
                    ->required()
                    ->options([
                        'ssl' => 'SSL',
                        'tls' => 'TLS',
                        'none' => 'None',
                    ])
                    ->default('ssl'),
                TextInput::make('username')
                    ->required()
                    ->maxLength(255),
                TextInput::make('password')
                    ->required()
                    ->password()
                    ->revealable()
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->default(true)
                    ->columnSpanFull(),
            ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data['user_id'] = auth()->id();

        return static::getModel()::create($data);
    }
}
