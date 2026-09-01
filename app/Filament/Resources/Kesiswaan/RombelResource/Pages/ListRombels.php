<?php

namespace App\Filament\Resources\Kesiswaan\RombelResource\Pages;

use App\Filament\Resources\Kesiswaan\RombelResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListRombels extends ListRecords
{
    protected static string $resource = RombelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Rombel'),
        ];
    }
}
