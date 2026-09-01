<?php

namespace App\Filament\Resources\Pos\PosBundleResource\Pages;

use App\Filament\Resources\Pos\PosBundleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPosBundle extends EditRecord
{
    protected static string $resource = PosBundleResource::class;

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
