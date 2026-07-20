<?php

namespace App\Filament\Pages;

use App\Models\SimulationSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;

/**
 * Lets an operator tune the timing assumptions the public simulation page
 * uses to animate trains (public/js/simulation.js), instead of those
 * numbers being hardcoded. In particular arrival_only_dwell_minutes: rows
 * that only have an arrival time (no departure recorded on that same row)
 * are shown "parked" for this many minutes as a fallback guess when the
 * simulation can't find a matching "Dinas Rangkaian" companion row to
 * link to (see ScheduleController + simulation.js). If that fallback
 * doesn't match real operations at a station, lower/raise it here rather
 * than editing code.
 */
class SimulationSettings extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?string $navigationLabel = 'Simulation Settings';

    protected static ?string $title = 'Simulation Settings';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.simulation-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(SimulationSetting::current()->only([
            'approach_minutes',
            'dwell_static_minutes',
            'arrival_only_dwell_minutes',
        ]));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('approach_minutes')
                    ->label('Entry/Exit Animation (minutes)')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(30)
                    ->helperText('How many minutes before arrival / after departure a train animates in or out of the station.'),
                TextInput::make('dwell_static_minutes')
                    ->label('Pre-Departure Static Display (minutes)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(30)
                    ->helperText('For departure-only rows: how many minutes before departure the train already appears parked at the platform.'),
                TextInput::make('arrival_only_dwell_minutes')
                    ->label('Default Arrival-Only Dwell (minutes)')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(180)
                    ->helperText('Fallback only: how long a train stays visible after arriving when it has no matching "Dinas Rangkaian" departure row on the same track to link to. If you see false collisions caused by trains "staying" too long, lower this.'),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SimulationSetting::current()->update($data);

        Notification::make()
            ->title('Simulation settings saved')
            ->success()
            ->send();
    }
}
