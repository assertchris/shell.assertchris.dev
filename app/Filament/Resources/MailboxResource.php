<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MailboxResource\Pages;
use App\Models\Mailbox;
use DirectoryTree\ImapEngine\Laravel\Facades\Imap;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MailboxResource extends Resource
{
    protected static ?string $model = Mailbox::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('host')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('port')
                    ->sortable(),
                TextColumn::make('encryption')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('test_connection')
                    ->label('Test')
                    ->action(function (Mailbox $record): void {
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
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMailboxes::route('/'),
            'create' => Pages\CreateMailbox::route('/create'),
            'edit' => Pages\EditMailbox::route('/{record}/edit'),
        ];
    }
}
