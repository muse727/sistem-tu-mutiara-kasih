<?php

namespace App\Filament\Resources\Pos\PosItemResource\Pages;

use App\Filament\Resources\Pos\PosItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPosItem extends EditRecord
{
    protected static string $resource = PosItemResource::class;

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
