<?php

namespace App\Filament\Resources\Pengaturan\UserResource\Pages;

use App\Filament\Resources\Pengaturan\UserResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Pengguna'),
        ];
    }
}
