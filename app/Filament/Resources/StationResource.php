<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StationResource\Pages;
use App\Models\Station;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StationResource extends Resource
{
    protected static ?string $model = Station::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Station / Relation';

    protected static ?string $modelLabel = 'Station';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->label('Code')
                ->required()
                ->maxLength(10)
                ->unique(ignoreRecord: true)
                ->helperText('Relation code as used on the schedule, e.g. SGU, SB, KTG'),
            Forms\Components\TextInput::make('name')
                ->label('Station Name')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('side')
                ->label('Direction / Yard Side')
                ->options([
                    'barat' => 'West',
                    'timur' => 'East',
                ])
                ->required()
                ->helperText('Determines which side this relation\'s trains enter/exit from in the simulation.'),
            Forms\Components\Toggle::make('is_own_station')
                ->label('Simulation station (has its own yard layout)')
                ->live()
                ->helperText('Enable so this station appears as a tab on the simulation page.'),
            Forms\Components\TextInput::make('km_position')
                ->label('KM Position')
                ->maxLength(255)
                ->visible(fn ($get) => $get('is_own_station'))
                ->helperText('e.g. "Km. 229+573"'),
            Forms\Components\TextInput::make('arah_barat_label')
                ->label('West Direction Label')
                ->maxLength(255)
                ->visible(fn ($get) => $get('is_own_station'))
                ->helperText('e.g. "Towards Wonokromo"'),
            Forms\Components\TextInput::make('arah_timur_label')
                ->label('East Direction Label')
                ->maxLength(255)
                ->visible(fn ($get) => $get('is_own_station'))
                ->helperText('e.g. "Towards Sidotopo / Surabaya Kota"'),
            Forms\Components\TextInput::make('sort_order')
                ->label('Tab Order')
                ->numeric()
                ->default(0)
                ->visible(fn ($get) => $get('is_own_station'))
                ->helperText('Tab display order, e.g. 1 = leftmost.'),
            Forms\Components\Textarea::make('keterangan')
                ->label('Notes')
                ->rows(2)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Code')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('side')
                    ->label('Direction')
                    ->badge()
                    ->color(fn (string $state) => $state === 'barat' ? 'primary' : 'success')
                    ->formatStateUsing(fn (string $state) => $state === 'barat' ? 'West' : 'East'),
                Tables\Columns\IconColumn::make('is_own_station')->label('Simulation Station')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Tab Order')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('side')->options([
                    'barat' => 'West',
                    'timur' => 'East',
                ]),
                Tables\Filters\TernaryFilter::make('is_own_station')->label('Simulation Station'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStations::route('/'),
            'create' => Pages\CreateStation::route('/create'),
            'edit' => Pages\EditStation::route('/{record}/edit'),
        ];
    }
}
