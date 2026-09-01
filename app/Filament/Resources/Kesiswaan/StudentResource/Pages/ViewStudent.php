<?php

namespace App\Filament\Resources\Kesiswaan\StudentResource\Pages;

use App\Filament\Resources\Kesiswaan\StudentResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

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
            Infolists\Components\Section::make('Identitas Siswa')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('nis')
                        ->label('NIS')
                        ->copyable()
                        ->fontFamily('mono'),

                    Infolists\Components\TextEntry::make('nisn')
                        ->label('NISN')
                        ->copyable()
                        ->fontFamily('mono'),

                    Infolists\Components\TextEntry::make('name')
                        ->label('Nama Lengkap')
                        ->weight('bold'),

                    Infolists\Components\TextEntry::make('birth_place')
                        ->label('Tempat Lahir'),

                    Infolists\Components\TextEntry::make('birth_date')
                        ->label('Tanggal Lahir')
                        ->date('d F Y'),

                    Infolists\Components\TextEntry::make('gender')
                        ->label('Jenis Kelamin')
                        ->formatStateUsing(fn ($state) => $state === 'L' ? 'Laki-laki' : 'Perempuan')
                        ->badge()
                        ->color(fn ($state) => $state === 'L' ? 'info' : 'warning'),

                    Infolists\Components\TextEntry::make('religion')
                        ->label('Agama'),

                    Infolists\Components\TextEntry::make('kelas.name')
                        ->label('Kelas')
                        ->badge()
                        ->color('info'),

                    Infolists\Components\TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->color(fn ($state) => match ($state) {
                            'active'    => 'success',
                            'graduated' => 'info',
                            'alumni'    => 'gray',
                            default     => 'warning',
                        })
                        ->formatStateUsing(fn ($state) => \App\Models\Student::STATUSES[$state] ?? $state),
                ]),

            Infolists\Components\Section::make('Kontak')
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('phone')
                        ->label('No. HP Siswa'),

                    Infolists\Components\TextEntry::make('parent_phone')
                        ->label('No. HP Orang Tua'),

                    Infolists\Components\TextEntry::make('address')
                        ->label('Alamat')
                        ->columnSpanFull(),
                ]),

            Infolists\Components\Section::make('Data Orang Tua')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Infolists\Components\TextEntry::make('father_name')
                        ->label('Nama Ayah'),

                    Infolists\Components\TextEntry::make('mother_name')
                        ->label('Nama Ibu'),

                    Infolists\Components\TextEntry::make('guardian_name')
                        ->label('Nama Wali'),

                    Infolists\Components\TextEntry::make('guardian_phone')
                        ->label('No. HP Wali'),
                ]),
        ]);
    }
}
