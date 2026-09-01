<?php

namespace App\Filament\Resources\Kesiswaan\RombelResource\Pages;

use App\Filament\Resources\Kesiswaan\RombelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRombel extends EditRecord
{
    protected static string $resource = RombelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
