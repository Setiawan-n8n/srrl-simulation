<?php

namespace App\Filament\Resources\StationResource\Pages;

use App\Filament\Resources\StationResource;
use App\Support\TableExporter;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStations extends ListRecords
{
    protected static string $resource = StationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action(fn () => TableExporter::excel(
                    'stations',
                    ['Code', 'Name', 'Direction', 'Simulation Station', 'Tab Order'],
                    $this->getTableQueryForExport()->get()->map(fn ($r) => [
                        $r->code,
                        $r->name,
                        $r->side === 'barat' ? 'West' : 'East',
                        $r->is_own_station ? 'Yes' : 'No',
                        $r->sort_order,
                    ]),
                )),
            Actions\Action::make('exportPdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => TableExporter::pdf(
                    'stations',
                    'Stations',
                    ['Code', 'Name', 'Direction', 'Sim. Station', 'Tab Order'],
                    $this->getTableQueryForExport()->get()->map(fn ($r) => [
                        $r->code,
                        $r->name,
                        $r->side === 'barat' ? 'West' : 'East',
                        $r->is_own_station ? 'Yes' : 'No',
                        $r->sort_order,
                    ]),
                    [1, 2, 1.5, 1.5, 1],
                )),
            Actions\CreateAction::make(),
        ];
    }
}
