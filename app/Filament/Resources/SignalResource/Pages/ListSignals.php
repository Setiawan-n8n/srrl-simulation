<?php

namespace App\Filament\Resources\SignalResource\Pages;

use App\Filament\Resources\SignalResource;
use App\Support\TableExporter;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSignals extends ListRecords
{
    protected static string $resource = SignalResource::class;

    protected function typeLabel(?string $state): string
    {
        return match ($state) {
            'masuk' => 'Home Signal',
            'keluar' => 'Starting Signal',
            'langsir' => 'Shunting Signal',
            'blok' => 'Block Signal',
            default => (string) $state,
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action(fn () => TableExporter::excel(
                    'signals',
                    ['Station', 'Code', 'Track', 'Side', 'Type', 'X', 'Y'],
                    $this->getTableQueryForExport()->get()->map(fn ($r) => [
                        $r->station?->name,
                        $r->code,
                        $r->track?->name,
                        $r->side === 'barat' ? 'West' : 'East',
                        $this->typeLabel($r->jenis),
                        $r->pos_x,
                        $r->pos_y,
                    ]),
                )),
            Actions\Action::make('exportPdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => TableExporter::pdf(
                    'signals',
                    'Signals',
                    ['Station', 'Code', 'Track', 'Side', 'Type', 'X', 'Y'],
                    $this->getTableQueryForExport()->get()->map(fn ($r) => [
                        $r->station?->name,
                        $r->code,
                        $r->track?->name,
                        $r->side === 'barat' ? 'West' : 'East',
                        $this->typeLabel($r->jenis),
                        $r->pos_x,
                        $r->pos_y,
                    ]),
                    [2, 1, 1.5, 1, 1.5, 0.7, 0.7],
                )),
            Actions\CreateAction::make(),
        ];
    }
}
