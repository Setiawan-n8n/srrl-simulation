<?php

namespace App\Filament\Resources\AccidentLogResource\Pages;

use App\Filament\Resources\AccidentLogResource;
use App\Support\TableExporter;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccidentLogs extends ListRecords
{
    protected static string $resource = AccidentLogResource::class;

    protected function rowToArray($r): array
    {
        return [
            $r->tanggal?->format('d M Y'),
            $r->clock_time,
            $r->station?->name,
            $r->track_code,
            trim(($r->train_a_no_ka ?? '-').' '.($r->train_a_nama ?? '')),
            trim(($r->train_b_no_ka ?? '-').' '.($r->train_b_nama ?? '')),
            $r->detail,
            $r->detected_at?->format('d M Y H:i'),
        ];
    }

    protected function getHeaderActions(): array
    {
        $headers = ['Date', 'Sim. Time', 'Station', 'Track', 'Train A', 'Train B', 'Detail', 'Detected At'];

        return [
            Actions\Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action(fn () => TableExporter::excel(
                    'accident-logs',
                    $headers,
                    $this->getTableQueryForExport()->with(['station', 'track'])->get()->map(fn ($r) => $this->rowToArray($r)),
                )),
            Actions\Action::make('exportPdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => TableExporter::pdf(
                    'accident-logs',
                    'Accident Log',
                    $headers,
                    $this->getTableQueryForExport()->with(['station', 'track'])->get()->map(fn ($r) => $this->rowToArray($r)),
                    [1, 0.7, 1.2, 0.7, 1.6, 1.6, 2.6, 1.3],
                )),
        ];
    }
}
