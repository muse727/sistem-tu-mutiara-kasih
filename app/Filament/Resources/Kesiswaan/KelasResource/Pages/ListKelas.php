<?php

namespace App\Filament\Resources\Kesiswaan\KelasResource\Pages;

use App\Filament\Resources\Kesiswaan\KelasResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListKelas extends ListRecords
{
    protected static string $resource = KelasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Kelas'),
        ];
    }
}
