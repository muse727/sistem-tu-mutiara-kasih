<?php

namespace App\Filament\Resources\Kesiswaan;

use App\Filament\Resources\Kesiswaan\KelasResource\Pages;
use App\Models\Kelas;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class KelasResource extends Resource
{
    protected static ?string $model = Kelas::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Kesiswaan';
    protected static ?string $navigationLabel = 'Master Kelas';
    protected static ?string $modelLabel = 'Kelas';
    protected static ?string $pluralModelLabel = 'Master Kelas';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Data Kelas')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('nama_kelas')
                        ->label('Nama Kelas')
                        ->required()
                        ->maxLength(50)
                        ->placeholder('Contoh: VII A, VIII B, IX C'),

                    Forms\Components\TextInput::make('tingkat')
                        ->label('Tingkat')
                        ->numeric()
                        ->placeholder('Contoh: 7, 8, 9'),

                    Forms\Components\TextInput::make('jurusan')
                        ->label('Jurusan')
                        ->maxLength(50)
                        ->nullable(),

                    Forms\Components\TextInput::make('wali_kelas')
                        ->label('Wali Kelas')
                        ->maxLength(100)
                        ->nullable(),

                    Forms\Components\Toggle::make('is_aktif')
                        ->label('Aktif')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama_kelas')
                    ->label('Nama Kelas')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('tingkat')
                    ->label('Tingkat')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('jurusan')
                    ->label('Jurusan')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('students_count')
                    ->label('Jml Siswa')
                    ->counts('students')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('wali_kelas')
                    ->label('Wali Kelas')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_aktif')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_aktif')->label('Status Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nama_kelas')
            ->striped();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListKelas::route('/'),
            'create' => Pages\CreateKelas::route('/create'),
            'edit'   => Pages\EditKelas::route('/{record}/edit'),
        ];
    }
}
