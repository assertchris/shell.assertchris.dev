<?php

namespace App\Filament\Resources\MailboxResource\Pages;

use App\Filament\Resources\MailboxResource;
use App\Models\Mailbox;
use DirectoryTree\ImapEngine\Laravel\Facades\Imap;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;

class EditMailbox extends EditRecord
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
                    ->password()
                    ->revealable()
                    ->dehydrateStateUsing(fn (?string $state, $record) => filled($state) ? $state : $record->password)
                    ->helperText('Leave blank to keep the current password.')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->default(true)
                    ->columnSpanFull(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test_connection')
                ->label('Test Connection')
                ->extraAttributes([
                    'data-test-btn' => true,
                    'wire:loading.attr' => 'disabled',
                    'wire:loading.class' => 'opacity-70 cursor-wait',
                    'wire:target' => 'mountAction(\'test_connection\')',
                ])
                ->action(fn () => $this->testConnection()),
            DeleteAction::make(),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Mailbox saved')
            ->body('Would you like to test the connection?')
            ->actions([
                Action::make('test_connection')
                    ->label('Test Connection')
                    ->button()
                    ->close()
                    ->dispatch('mountTestConnection'),
            ])
            ->persistent();
    }

    #[On('mountTestConnection')]
    public function mountTestConnection(): void
    {
        $this->js("document.querySelector('[data-test-btn]')?.click()");
    }

    public function testConnection(): void
    {
        /** @var Mailbox $record */
        $record = $this->getRecord();

        try {
            $mailbox = Imap::build($record->toImapConfig());
            $mailbox->connect();
            $mailbox->disconnect();

            Notification::make()
                ->success()
                ->title('Connection successful')
                ->body("Connected to {$record->host} successfully.")
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Connection failed')
                ->body($e->getMessage())
                ->send();
        }
    }
}
