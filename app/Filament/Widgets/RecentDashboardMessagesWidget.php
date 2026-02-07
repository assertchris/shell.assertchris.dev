<?php

namespace App\Filament\Widgets;

use App\Enums\DashboardMessagePriority;
use App\Filament\Resources\DashboardMessageResource;
use App\Models\DashboardMessage;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentDashboardMessagesWidget extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Messages')
            ->query(
                DashboardMessage::query()
                    ->where('user_id', auth()->id())
                    ->whereNull('read_at')
                    ->whereNull('archived_at')
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('title')
                    ->url(fn (DashboardMessage $record): string => DashboardMessageResource::getUrl('view', ['record' => $record])),
                TextColumn::make('priority')
                    ->badge()
                    ->color(fn (DashboardMessagePriority $state): string => match ($state) {
                        DashboardMessagePriority::Urgent => 'danger',
                        DashboardMessagePriority::High => 'warning',
                        DashboardMessagePriority::Normal => 'info',
                        DashboardMessagePriority::Low => 'gray',
                    }),
                TextColumn::make('source')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->since(),
            ])
            ->paginated(false);
    }
}
