<?php

namespace App\Filament\Resources\Kesiswaan\KelasResource\Pages;

use App\Filament\Resources\Kesiswaan\KelasResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKelas extends CreateRecord
{
    protected static string $resource = KelasResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
