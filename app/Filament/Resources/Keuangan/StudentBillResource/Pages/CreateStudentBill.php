<?php

namespace App\Filament\Resources\Keuangan\StudentBillResource\Pages;

use App\Filament\Resources\Keuangan\StudentBillResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStudentBill extends CreateRecord
{
    protected static string $resource = StudentBillResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
