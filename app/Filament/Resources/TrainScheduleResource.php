<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrainScheduleResource\Pages;
use App\Models\TrainSchedule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TrainScheduleResource extends Resource
{
    protected static ?string $model = TrainSchedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Schedule';

    protected static ?string $navigationLabel = 'Train Schedule';

    protected static ?string $modelLabel = 'Train Schedule';

    protected static ?int $navigationSort = 0;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('tanggal')->label('Date')->required(),
            Forms\Components\TextInput::make('urutan')->label('No.')->numeric()->default(0),
            Forms\Components\TextInput::make('no_ka')->label('Train No.')->required()->maxLength(30),
            Forms\Components\TextInput::make('nama_ka')->label('Train Name')->required()->maxLength(255),
            Forms\Components\Select::make('relasi_asal_id')
                ->label('Origin')
                ->relationship('asal', 'code')
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('relasi_tujuan_id')
                ->label('Destination')
                ->relationship('tujuan', 'code')
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('relasi_raw')->label('Relation (raw text)')->maxLength(60),
            Forms\Components\TimePicker::make('jam_datang')->label('Arrival Time')->seconds(false),
            Forms\Components\TextInput::make('jam_datang_ket')->label('Arrival Note (e.g. Ls)')->maxLength(20),
            Forms\Components\TimePicker::make('jam_berangkat')->label('Departure Time')->seconds(false),
            Forms\Components\TextInput::make('jam_berangkat_ket')->label('Departure Note')->maxLength(20),
            Forms\Components\Select::make('track_id')
                ->label('Track')
                ->relationship('track', 'name')
                ->searchable()
                ->preload(),
            Forms\Components\Textarea::make('catatan')->label('Notes')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tanggal')->label('Date')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('urutan')->label('No.')->sortable(),
                Tables\Columns\TextColumn::make('no_ka')->label('Train No.')->searchable(),
                Tables\Columns\TextColumn::make('nama_ka')->label('Train Name')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('relasi_raw')->label('Relation'),
                Tables\Columns\TextColumn::make('jam_datang')->label('Arrival')
                    ->formatStateUsing(fn ($state, $record) => $state ? $state->format('H:i') : ($record->jam_datang_ket ?? '-')),
                Tables\Columns\TextColumn::make('jam_berangkat')->label('Departure')
                    ->formatStateUsing(fn ($state, $record) => $state ? $state->format('H:i') : ($record->jam_berangkat_ket ?? '-')),
                Tables\Columns\TextColumn::make('track.code')->label('Track')->badge()->color('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tanggal')
                    ->options(fn () => TrainSchedule::query()->distinct()->pluck('tanggal', 'tanggal')->mapWithKeys(fn ($v) => [$v->format('Y-m-d') => $v->format('d M Y')])->all()),
                Tables\Filters\SelectFilter::make('track_id')->relationship('track', 'name')->label('Track'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('urutan');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainSchedules::route('/'),
            'create' => Pages\CreateTrainSchedule::route('/create'),
            'edit' => Pages\EditTrainSchedule::route('/{record}/edit'),
        ];
    }
}
