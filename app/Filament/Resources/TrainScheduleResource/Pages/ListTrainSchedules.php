<?php

namespace App\Filament\Resources\TrainScheduleResource\Pages;

use App\Filament\Resources\TrainScheduleResource;
use App\Support\JadwalImporter;
use App\Support\TableExporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListTrainSchedules extends ListRecords
{
    protected static string $resource = TrainScheduleResource::class;

    protected function timeOrNote($state, $note): string
    {
        return $state ? $state->format('H:i') : ($note ?? '-');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action(fn () => TableExporter::excel(
                    'train-schedules',
                    ['Date', 'No.', 'Train No.', 'Train Name', 'Relation', 'Arrival', 'Departure', 'Track'],
                    $this->getTableQueryForExport()->with('track')->get()->map(fn ($r) => [
                        $r->tanggal?->format('d M Y'),
                        $r->urutan,
                        $r->no_ka,
                        $r->nama_ka,
                        $r->relasi_raw,
                        $this->timeOrNote($r->jam_datang, $r->jam_datang_ket),
                        $this->timeOrNote($r->jam_berangkat, $r->jam_berangkat_ket),
                        $r->track?->code,
                    ]),
                )),
            Actions\Action::make('exportPdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => TableExporter::pdf(
                    'train-schedules',
                    'Train Schedule',
                    ['Date', 'No.', 'Train No.', 'Train Name', 'Relation', 'Arrival', 'Departure', 'Track'],
                    $this->getTableQueryForExport()->with('track')->get()->map(fn ($r) => [
                        $r->tanggal?->format('d M Y'),
                        $r->urutan,
                        $r->no_ka,
                        $r->nama_ka,
                        $r->relasi_raw,
                        $this->timeOrNote($r->jam_datang, $r->jam_datang_ket),
                        $this->timeOrNote($r->jam_berangkat, $r->jam_berangkat_ket),
                        $r->track?->code,
                    ]),
                    [1.3, 0.6, 1, 2, 1, 0.8, 0.8, 0.7],
                )),
            Actions\Action::make('import')
                ->label('Import Schedule (Excel)')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Excel File (.xlsx)')
                        ->disk('local')
                        ->directory('jadwal-import')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->required(),
                    Forms\Components\DatePicker::make('tanggal')
                        ->label('Schedule Date')
                        ->required()
                        ->default(now()),
                    Forms\Components\TextInput::make('sheet')
                        ->label('Sheet Name')
                        ->default('Sheet1'),
                ])
                ->action(function (array $data) {
                    $path = storage_path('app/'.$data['file']);

                    $count = JadwalImporter::importFromFile(
                        $path,
                        $data['tanggal'],
                        $data['sheet'] ?: 'Sheet1'
                    );

                    Notification::make()
                        ->title("Successfully imported {$count} schedule rows")
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
