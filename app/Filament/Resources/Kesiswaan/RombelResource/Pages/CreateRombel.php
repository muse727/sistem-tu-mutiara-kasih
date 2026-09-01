<?php

namespace App\Filament\Resources\Kesiswaan\RombelResource\Pages;

use App\Filament\Resources\Kesiswaan\RombelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRombel extends CreateRecord
{
    protected static string $resource = RombelResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
