<?php

namespace App\Filament\Resources\TrackResource\Pages;

use App\Filament\Resources\TrackResource;
use App\Support\TableExporter;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTracks extends ListRecords
{
    protected static string $resource = TrackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action(fn () => TableExporter::excel(
                    'tracks',
                    ['Station', 'Code', 'Name', 'Type', 'Schedule Count'],
                    $this->getTableQueryForExport()->withCount('schedules')->get()->map(fn ($r) => [
                        $r->station?->name,
                        $r->code,
                        $r->name,
                        $r->jenis,
                        $r->schedules_count,
                    ]),
                )),
            Actions\Action::make('exportPdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => TableExporter::pdf(
                    'tracks',
                    'Tracks',
                    ['Station', 'Code', 'Name', 'Type', 'Sched. Count'],
                    $this->getTableQueryForExport()->withCount('schedules')->get()->map(fn ($r) => [
                        $r->station?->name,
                        $r->code,
                        $r->name,
                        $r->jenis,
                        $r->schedules_count,
                    ]),
                    [2, 1, 1.5, 2, 1],
                )),
            Actions\CreateAction::make(),
        ];
    }
}
