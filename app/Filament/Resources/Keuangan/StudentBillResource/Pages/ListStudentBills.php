<?php

namespace App\Filament\Resources\Keuangan\StudentBillResource\Pages;

use App\Filament\Resources\Keuangan\StudentBillResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStudentBills extends ListRecords
{
    protected static string $resource = StudentBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Buat Tagihan'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua')
                ->badge(fn () => \App\Models\StudentBill::count()),

            'unpaid' => Tab::make('Belum Bayar')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'UNPAID'))
                ->badge(fn () => \App\Models\StudentBill::where('status', 'UNPAID')->count())
                ->badgeColor('danger'),

            'partial' => Tab::make('Sebagian')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'PARTIAL'))
                ->badge(fn () => \App\Models\StudentBill::where('status', 'PARTIAL')->count())
                ->badgeColor('warning'),

            'paid' => Tab::make('Lunas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'PAID'))
                ->badge(fn () => \App\Models\StudentBill::where('status', 'PAID')->count())
                ->badgeColor('success'),

            'overdue' => Tab::make('Jatuh Tempo')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', 'UNPAID')
                    ->whereDate('due_date', '<', now()))
                ->badge(fn () => \App\Models\StudentBill::where('status', 'UNPAID')
                    ->whereDate('due_date', '<', now())->count())
                ->badgeColor('danger'),
        ];
    }
}
