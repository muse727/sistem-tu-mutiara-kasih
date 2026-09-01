<?php

namespace App\Filament\Resources\Pos\PosBundleResource\Pages;

use App\Filament\Resources\Pos\PosBundleResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListPosBundles extends ListRecords
{
    protected static string $resource = PosBundleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Buat Paket'),
        ];
    }
}
