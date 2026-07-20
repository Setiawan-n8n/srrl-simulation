<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrainResource\Pages;
use App\Models\Train;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TrainResource extends Resource
{
    protected static ?string $model = Train::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Train';

    protected static ?string $modelLabel = 'Train';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('no_ka')->label('Train No.')->required()->maxLength(30),
            Forms\Components\TextInput::make('nama')->label('Train Name')->required()->maxLength(255),
            Forms\Components\Select::make('kategori')
                ->label('Category')
                ->options([
                    'penumpang' => 'Passenger',
                    'barang' => 'Freight',
                    'komuter' => 'Commuter',
                    'langsir' => 'Shunting',
                    'dinas' => 'Duty Consist',
                    'lainnya' => 'Other',
                ])
                ->required(),
            Forms\Components\Textarea::make('keterangan')->label('Notes')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('no_ka')->label('Train No.')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('nama')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('kategori')->label('Category')->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'penumpang' => 'Passenger',
                        'barang' => 'Freight',
                        'komuter' => 'Commuter',
                        'langsir' => 'Shunting',
                        'dinas' => 'Duty Consist',
                        'lainnya' => 'Other',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'penumpang' => 'success',
                        'barang' => 'warning',
                        'komuter' => 'info',
                        'langsir' => 'gray',
                        'dinas' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('schedules_count')->label('Schedule Count')->counts('schedules'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kategori')->options([
                    'penumpang' => 'Passenger',
                    'barang' => 'Freight',
                    'komuter' => 'Commuter',
                    'langsir' => 'Shunting',
                    'dinas' => 'Duty Consist',
                    'lainnya' => 'Other',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('no_ka');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrains::route('/'),
            'create' => Pages\CreateTrain::route('/create'),
            'edit' => Pages\EditTrain::route('/{record}/edit'),
        ];
    }
}
