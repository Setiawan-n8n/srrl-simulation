<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccidentLogResource\Pages;
use App\Models\AccidentLog;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Read-only log of collisions the simulation detected (two different
 * trains rendered on the same track with overlapping positions at the
 * same simulated time — see detectCollisions() in public/js/simulation.js).
 * Rows are written by App\Http\Controllers\Api\AccidentLogController, not
 * created manually here, so this resource only offers list + delete.
 */
class AccidentLogResource extends Resource
{
    protected static ?string $model = AccidentLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?string $navigationLabel = 'Accident Log';

    protected static ?string $modelLabel = 'Accident Log';

    protected static ?int $navigationSort = 0;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tanggal')->label('Date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('clock_time')->label('Sim. Time')->sortable(),
                Tables\Columns\TextColumn::make('station.name')->label('Station')->sortable(),
                Tables\Columns\TextColumn::make('track_code')->label('Track')->badge()->color('danger'),
                Tables\Columns\TextColumn::make('train_a_no_ka')->label('Train A')
                    ->formatStateUsing(fn ($state, $record) => trim(($state ?? '-').' '.($record->train_a_nama ?? ''))),
                Tables\Columns\TextColumn::make('train_b_no_ka')->label('Train B')
                    ->formatStateUsing(fn ($state, $record) => trim(($state ?? '-').' '.($record->train_b_nama ?? ''))),
                Tables\Columns\TextColumn::make('detail')->label('Detail')->limit(60)->wrap(),
                Tables\Columns\TextColumn::make('detected_at')->label('Detected At')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('detected_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tanggal')
                    ->options(fn () => AccidentLog::query()->distinct()->pluck('tanggal', 'tanggal')->mapWithKeys(fn ($v) => [$v->format('Y-m-d') => $v->format('d M Y')])->all()),
                Tables\Filters\SelectFilter::make('track_id')->relationship('track', 'name')->label('Track'),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccidentLogs::route('/'),
        ];
    }
}
