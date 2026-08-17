<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrainScheduleResource\Pages;
use App\Models\Track;
use App\Models\TrainSchedule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            Forms\Components\Select::make('station_id')
                ->label('Station (emplasemen)')
                ->helperText('Jadwal ini milik stasiun mana -- WAJIB diisi, kalau kosong baris ini tidak akan muncul di halaman simulasi manapun.')
                ->relationship('station', 'name', fn ($query) => $query->simulasi())
                ->required()
                ->live()
                ->searchable()
                ->preload(),
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
            // Dibatasi ke jalur milik station_id yang dipilih di atas --
            // beberapa stasiun berbagi kode jalur yang sama persis (mis.
            // SGU & SDT sama-sama punya jalur "VI"), jadi daftar ini HARUS
            // discope per-stasiun supaya tidak salah pilih jalur stasiun
            // lain (nama jalur ditampilkan sebagai "Jalur VI" tanpa info
            // stasiun, gampang salah pilih kalau tidak difilter).
            Forms\Components\Select::make('track_id')
                ->label('Track')
                ->options(fn (Forms\Get $get) => $get('station_id')
                    ? Track::query()->where('station_id', $get('station_id'))->orderBy('sort_order')->pluck('name', 'id')
                    : [])
                ->helperText('Pilih Station dulu supaya daftar jalur sesuai.')
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('gan_gen')->label('Gan-Gen')->maxLength(60),
            Forms\Components\TextInput::make('waktu_tinggal_menit')->label('Waktu Tinggal (menit)')->numeric()->minValue(0),
            Forms\Components\Textarea::make('catatan')->label('Keterangan')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('station.code')->label('Station')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->default('(kosong!)')
                    ->description(fn ($record) => $record->station_id ? null : 'station_id kosong -- baris ini TIDAK akan muncul di simulasi manapun')
                    ->sortable(),
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
                Tables\Columns\TextColumn::make('gan_gen')->label('Gan-Gen')->toggleable()->toggledHiddenByDefault(),
                Tables\Columns\TextColumn::make('waktu_tinggal_menit')->label('Waktu Tinggal (menit)')->toggleable()->toggledHiddenByDefault(),
                Tables\Columns\TextColumn::make('catatan')->label('Keterangan')->limit(30)->toggleable()->toggledHiddenByDefault(),
            ])
            ->filters([
                // PENTING: SelectFilter bawaan Filament tanpa ->query() custom
                // akan melakukan where('tanggal', $value) polos -- sama
                // seperti bug yang diperbaiki di TrainScheduleSeeder &
                // JadwalImporter (lihat README), kolom `tanggal` tersimpan
                // sebagai datetime penuh ("2026-08-12 00:00:00") sehingga
                // perbandingan string mentah TIDAK PERNAH cocok dan filter
                // ini akan selalu menghasilkan "No Train Schedules" walau
                // datanya ada. ->query() di bawah pakai whereDate() supaya
                // benar-benar cocok.
                Tables\Filters\SelectFilter::make('tanggal')
                    ->options(fn () => TrainSchedule::query()->distinct()->pluck('tanggal', 'tanggal')->mapWithKeys(fn ($v) => [$v->format('Y-m-d') => $v->format('d M Y')])->all())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $value) => $q->whereDate('tanggal', $value)
                    )),
                Tables\Filters\SelectFilter::make('station_id')->relationship('station', 'name')->label('Station'),
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
