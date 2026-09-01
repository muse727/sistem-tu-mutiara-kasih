<?php

namespace App\Filament\Resources\Kesiswaan\StudentResource\Pages;

use App\Filament\Resources\Kesiswaan\StudentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
