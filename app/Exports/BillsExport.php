<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\Export;

class BillsExport implements WithMultipleSheets, Export
{
    use Exportable;

    protected Collection $bills;
    protected array $filters;
    protected array $school;

    public function __construct(Collection $bills, array $filters = [], array $school = [])
    {
        $this->bills   = $bills;
        $this->filters = $filters;
        $this->school  = $school;
    }

    public function sheets(): array
    {
        return [
            new Sheets\BillsSummarySheet($this->bills, $this->filters, $this->school),
            new Sheets\BillsDetailSheet($this->bills),
        ];
    }
}
