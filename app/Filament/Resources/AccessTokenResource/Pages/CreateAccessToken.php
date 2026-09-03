<?php

namespace App\Filament\Resources\AccessTokenResource\Pages;

use App\Filament\Resources\AccessTokenResource;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateAccessToken extends CreateRecord
{
    protected static string $resource = AccessTokenResource::class;

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                DateTimePicker::make('expires_at')
                    ->label('Expires at')
                    ->helperText('Leave blank for a token that never expires.')
                    ->columnSpanFull(),
            ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();

        $token = $user->createToken(
            name: $data['name'],
            expiresAt: filled($data['expires_at'] ?? null) ? Carbon::parse($data['expires_at']) : null,
        );

        $this->plainTextToken = $token->plainTextToken;

        return $token->accessToken;
    }

    protected ?string $plainTextToken = null;

    protected function afterCreate(): void
    {
        Notification::make()
            ->success()
            ->title('Token created')
            ->body("Copy your token now — it won't be shown again:<br><br><code>{$this->plainTextToken}</code>")
            ->persistent()
            ->send();

        $this->halt();
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }
}
