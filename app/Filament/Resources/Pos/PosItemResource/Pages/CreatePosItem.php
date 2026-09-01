<?php

namespace App\Filament\Resources\Pos\PosItemResource\Pages;

use App\Filament\Resources\Pos\PosItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePosItem extends CreateRecord
{
    protected static string $resource = PosItemResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
