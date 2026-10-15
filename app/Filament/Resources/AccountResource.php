<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountResource\Pages;
use App\Models\Account;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

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
                IconColumn::make('mail_used')
                    ->label('Mail')
                    ->boolean(),
                IconColumn::make('google_calendar_used')
                    ->label('Calendar')
                    ->boolean(),
                IconColumn::make('slack_used')
                    ->label('Slack')
                    ->boolean(),
                IconColumn::make('github_used')
                    ->label('GitHub')
                    ->boolean(),
                IconColumn::make('jira_used')
                    ->label('Jira')
                    ->boolean(),
                IconColumn::make('laravel_cloud_used')
                    ->label('Laravel Cloud')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make()->icon(null),
                DeleteAction::make()->icon(null),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}
