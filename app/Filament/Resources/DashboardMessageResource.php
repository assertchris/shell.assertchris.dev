<?php

namespace App\Filament\Resources;

use App\Enums\DashboardMessagePriority;
use App\Filament\Resources\DashboardMessageResource\Pages;
use App\Models\DashboardMessage;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DashboardMessageResource extends Resource
{
    protected static ?string $model = DashboardMessage::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $navigationLabel = 'Messages';

    protected static ?string $modelLabel = 'Message';

    protected static ?string $pluralModelLabel = 'Messages';

    public static function getNavigationBadge(): ?string
    {
        $count = DashboardMessage::query()
            ->where('user_id', auth()->id())
            ->whereNull('read_at')
            ->whereNull('archived_at')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * @return string|array<string>|null
     */
    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('priority')
                    ->badge()
                    ->color(fn (DashboardMessagePriority $state): string => match ($state) {
                        DashboardMessagePriority::Urgent => 'danger',
                        DashboardMessagePriority::High => 'warning',
                        DashboardMessagePriority::Normal => 'info',
                        DashboardMessagePriority::Low => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('source')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('read_at')
                    ->label('Read')
                    ->boolean()
                    ->getStateUsing(fn (DashboardMessage $record): bool => $record->read_at !== null),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('read')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('read_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('read_at'),
                    ),
                SelectFilter::make('priority')
                    ->options(DashboardMessagePriority::class),
                TernaryFilter::make('archived')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('archived_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('archived_at'),
                    )
                    ->default(false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDashboardMessages::route('/'),
            'view' => Pages\ViewDashboardMessage::route('/{record}'),
        ];
    }
}
