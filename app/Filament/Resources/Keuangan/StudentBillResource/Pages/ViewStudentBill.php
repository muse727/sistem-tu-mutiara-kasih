<?php

namespace App\Filament\Resources\Keuangan\StudentBillResource\Pages;

use App\Filament\Resources\Keuangan\StudentBillResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewStudentBill extends ViewRecord
{
    protected static string $resource = StudentBillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Detail Tagihan')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('student.name')
                        ->label('Nama Siswa')
                        ->weight('bold'),

                    Infolists\Components\TextEntry::make('student.nis')
                        ->label('NIS')
                        ->fontFamily('mono'),

                    Infolists\Components\TextEntry::make('student.kelas.name')
                        ->label('Kelas')
                        ->badge()
                        ->color('info'),

                    Infolists\Components\TextEntry::make('description')
                        ->label('Keterangan')
                        ->columnSpanFull(),

                    Infolists\Components\TextEntry::make('amount')
                        ->label('Jumlah Tagihan')
                        ->money('IDR'),

                    Infolists\Components\TextEntry::make('discount')
                        ->label('Diskon')
                        ->money('IDR'),

                    Infolists\Components\TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->color(fn ($state) => match ($state) {
                            'PAID'    => 'success',
                            'PARTIAL' => 'warning',
                            'UNPAID'  => 'danger',
                            default   => 'gray',
                        })
                        ->formatStateUsing(fn ($state) => match ($state) {
                            'PAID'    => 'Lunas',
                            'PARTIAL' => 'Dibayar Sebagian',
                            'UNPAID'  => 'Belum Bayar',
                            default   => $state,
                        }),

                    Infolists\Components\TextEntry::make('due_date')
                        ->label('Jatuh Tempo')
                        ->date('d F Y'),

                    Infolists\Components\TextEntry::make('paid_at')
                        ->label('Dibayar Pada')
                        ->dateTime('d F Y, H:i'),

                    Infolists\Components\TextEntry::make('payment_method')
                        ->label('Metode Pembayaran')
                        ->badge()
                        ->color('gray'),

                    Infolists\Components\TextEntry::make('notes')
                        ->label('Catatan')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
