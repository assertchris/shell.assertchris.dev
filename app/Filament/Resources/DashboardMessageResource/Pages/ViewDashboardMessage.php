<?php

namespace App\Filament\Resources\DashboardMessageResource\Pages;

use App\Enums\DashboardMessagePriority;
use App\Filament\Resources\DashboardMessageResource;
use App\Models\DashboardMessage;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class ViewDashboardMessage extends ViewRecord
{
    protected static string $resource = DashboardMessageResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var DashboardMessage $message */
        $message = $this->getRecord();

        if ($message->read_at === null) {
            $message->update(['read_at' => now()]);
        }
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title')
                    ->hiddenLabel()
                    ->size(TextSize::Large)
                    ->weight(FontWeight::Bold)
                    ->columnSpanFull(),
                TextEntry::make('priority')
                    ->badge()
                    ->color(fn (DashboardMessage $record): string => match ($record->priority) {
                        DashboardMessagePriority::Urgent => 'danger',
                        DashboardMessagePriority::High => 'warning',
                        DashboardMessagePriority::Normal => 'info',
                        DashboardMessagePriority::Low => 'gray',
                    }),
                TextEntry::make('source')
                    ->placeholder('—'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('body')
                    ->html()
                    ->columnSpanFull(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggle_read')
                ->label(fn (): string => $this->getRecord()->read_at ? 'Mark Unread' : 'Mark Read')
                ->icon(fn (): Heroicon => $this->getRecord()->read_at ? Heroicon::OutlinedEnvelopeOpen : Heroicon::OutlinedEnvelope)
                ->action(function (): void {
                    $record = $this->getRecord();
                    $record->update([
                        'read_at' => $record->read_at ? null : now(),
                    ]);
                }),
            Action::make('toggle_archive')
                ->label(fn (): string => $this->getRecord()->archived_at ? 'Unarchive' : 'Archive')
                ->icon(fn (): Heroicon => $this->getRecord()->archived_at ? Heroicon::OutlinedArchiveBox : Heroicon::OutlinedArchiveBoxArrowDown)
                ->action(function (): void {
                    $record = $this->getRecord();
                    $record->update([
                        'archived_at' => $record->archived_at ? null : now(),
                    ]);
                }),
            DeleteAction::make(),
        ];
    }
}
