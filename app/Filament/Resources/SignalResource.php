<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SignalResource\Pages;
use App\Models\Signal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SignalResource extends Resource
{
    protected static ?string $model = Signal::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationGroup = 'Signaling';

    protected static ?string $navigationLabel = 'Signal';

    protected static ?string $modelLabel = 'Signal';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('station_id')
                ->label('Station')
                ->relationship('station', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->live(),
            Forms\Components\TextInput::make('code')->label('Signal Code')->required()->maxLength(20),
            Forms\Components\Select::make('track_id')
                ->label('Track')
                ->relationship('track', 'name', fn ($query, $get) => $query->when($get('station_id'), fn ($q, $sid) => $q->where('station_id', $sid)))
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('side')
                ->label('Side')
                ->options(['barat' => 'West (Wonokromo)', 'timur' => 'East (Sidotopo)'])
                ->required(),
            Forms\Components\Select::make('jenis')
                ->label('Type')
                ->options([
                    'masuk' => 'Home Signal',
                    'keluar' => 'Starting Signal',
                    'langsir' => 'Shunting Signal',
                    'blok' => 'Block Signal',
                ])
                ->required(),
            Forms\Components\TextInput::make('posisi_km')->label('KM Position')->numeric(),
            Forms\Components\TextInput::make('pos_x')->label('X Position (SVG layout, 0-1200)')->numeric(),
            Forms\Components\TextInput::make('pos_y')->label('Y Position (SVG layout, 0-500)')->numeric(),
            Forms\Components\Textarea::make('keterangan')->label('Notes')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('station.name')->label('Station')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('code')->label('Code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('track.name')->label('Track'),
                Tables\Columns\TextColumn::make('side')->label('Side')->badge()->color(fn (string $state) => $state === 'barat' ? 'primary' : 'success')->formatStateUsing(fn ($state) => $state === 'barat' ? 'West' : 'East'),
                Tables\Columns\TextColumn::make('jenis')->label('Type')->formatStateUsing(fn (?string $state) => match ($state) {
                    'masuk' => 'Home Signal',
                    'keluar' => 'Starting Signal',
                    'langsir' => 'Shunting Signal',
                    'blok' => 'Block Signal',
                    default => $state,
                }),
                Tables\Columns\TextColumn::make('pos_x')->label('X'),
                Tables\Columns\TextColumn::make('pos_y')->label('Y'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('station_id')->relationship('station', 'name')->label('Station'),
                Tables\Filters\SelectFilter::make('track_id')->relationship('track', 'name')->label('Track'),
                Tables\Filters\SelectFilter::make('side')->options(['barat' => 'West', 'timur' => 'East']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSignals::route('/'),
            'create' => Pages\CreateSignal::route('/create'),
            'edit' => Pages\EditSignal::route('/{record}/edit'),
        ];
    }
}
