<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;

class BillsExport implements WithMultipleSheets, \Maatwebsite\Excel\Concerns\Export
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
