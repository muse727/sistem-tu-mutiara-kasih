<?php

namespace App\Filament\Resources\Pos\PosItemResource\Pages;

use App\Filament\Resources\Pos\PosItemResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListPosItems extends ListRecords
{
    protected static string $resource = PosItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Barang'),
        ];
    }
}
