<?php

namespace App\Filament\Resources\TrainResource\Pages;

use App\Filament\Resources\TrainResource;
use App\Support\TableExporter;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTrains extends ListRecords
{
    protected static string $resource = TrainResource::class;

    protected function categoryLabel(?string $state): string
    {
        return match ($state) {
            'penumpang' => 'Passenger',
            'barang' => 'Freight',
            'komuter' => 'Commuter',
            'langsir' => 'Shunting',
            'dinas' => 'Duty Consist',
            'lainnya' => 'Other',
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
                    'trains',
                    ['Train No.', 'Name', 'Category', 'Schedule Count'],
                    $this->getTableQueryForExport()->withCount('schedules')->get()->map(fn ($r) => [
                        $r->no_ka,
                        $r->nama,
                        $this->categoryLabel($r->kategori),
                        $r->schedules_count,
                    ]),
                )),
            Actions\Action::make('exportPdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => TableExporter::pdf(
                    'trains',
                    'Trains',
                    ['Train No.', 'Name', 'Category', 'Sched. Count'],
                    $this->getTableQueryForExport()->withCount('schedules')->get()->map(fn ($r) => [
                        $r->no_ka,
                        $r->nama,
                        $this->categoryLabel($r->kategori),
                        $r->schedules_count,
                    ]),
                    [1, 2, 1.5, 1],
                )),
            Actions\CreateAction::make(),
        ];
    }
}
