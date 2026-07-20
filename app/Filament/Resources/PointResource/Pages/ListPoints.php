<?php

namespace App\Filament\Resources\PointResource\Pages;

use App\Filament\Resources\PointResource;
use App\Support\TableExporter;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPoints extends ListRecords
{
    protected static string $resource = PointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action(fn () => TableExporter::excel(
                    'switches',
                    ['Station', 'Code', 'From Track', 'To Track', 'Side'],
                    $this->getTableQueryForExport()->get()->map(fn ($r) => [
                        $r->station?->name,
                        $r->code,
                        $r->trackFrom?->name,
                        $r->trackTo?->name,
                        $r->side === 'barat' ? 'West' : 'East',
                    ]),
                )),
            Actions\Action::make('exportPdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => TableExporter::pdf(
                    'switches',
                    'Switches',
                    ['Station', 'Code', 'From Track', 'To Track', 'Side'],
                    $this->getTableQueryForExport()->get()->map(fn ($r) => [
                        $r->station?->name,
                        $r->code,
                        $r->trackFrom?->name,
                        $r->trackTo?->name,
                        $r->side === 'barat' ? 'West' : 'East',
                    ]),
                    [2, 1, 1.5, 1.5, 1],
                )),
            Actions\CreateAction::make(),
        ];
    }
}
