<?php

namespace App\Filament\Resources\Pos\PosBundleResource\Pages;

use App\Filament\Resources\Pos\PosBundleResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePosBundle extends CreateRecord
{
    protected static string $resource = PosBundleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
