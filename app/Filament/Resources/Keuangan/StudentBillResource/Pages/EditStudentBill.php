<?php

namespace App\Filament\Resources\Keuangan\StudentBillResource\Pages;

use App\Filament\Resources\Keuangan\StudentBillResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStudentBill extends EditRecord
{
    protected static string $resource = StudentBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
