<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrackResource\Pages;
use App\Models\Track;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TrackResource extends Resource
{
    protected static ?string $model = Track::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Track / Yard';

    protected static ?string $modelLabel = 'Track';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('station_id')
                ->label('Station')
                ->relationship('station', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\TextInput::make('code')->label('Track Code')->required()->maxLength(20),
            Forms\Components\TextInput::make('name')->label('Name')->required()->maxLength(255),
            Forms\Components\TextInput::make('jenis')->label('Track Type')->maxLength(255),
            Forms\Components\TextInput::make('sort_order')->label('Display Order')->numeric()->default(0),
            Forms\Components\Textarea::make('keterangan')->label('Notes')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('station.name')->label('Station')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('code')->label('Code')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('jenis')->label('Type'),
                Tables\Columns\TextColumn::make('schedules_count')->label('Schedule Count')->counts('schedules'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('station_id')->relationship('station', 'name')->label('Station'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTracks::route('/'),
            'create' => Pages\CreateTrack::route('/create'),
            'edit' => Pages\EditTrack::route('/{record}/edit'),
        ];
    }
}
