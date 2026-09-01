<?php

namespace App\Filament\Resources\Kesiswaan\StudentResource\Pages;

use App\Filament\Resources\Kesiswaan\StudentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua')
                ->badge(fn () => \App\Models\Student::withTrashed()->count()),

            'active' => Tab::make('Aktif')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active'))
                ->badge(fn () => \App\Models\Student::where('status', 'active')->count())
                ->badgeColor('success'),

            'graduated' => Tab::make('Lulus')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'graduated'))
                ->badge(fn () => \App\Models\Student::where('status', 'graduated')->count())
                ->badgeColor('info'),

            'alumni' => Tab::make('Alumni')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'alumni'))
                ->badge(fn () => \App\Models\Student::where('status', 'alumni')->count())
                ->badgeColor('gray'),

            'trashed' => Tab::make('Diarsipkan')
                ->modifyQueryUsing(fn (Builder $query) => $query->onlyTrashed())
                ->badge(fn () => \App\Models\Student::onlyTrashed()->count())
                ->badgeColor('danger'),
        ];
    }
}
