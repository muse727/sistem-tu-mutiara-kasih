<?php

namespace App\Filament\Resources\Kesiswaan;

use App\Filament\Resources\Kesiswaan\RombelResource\Pages;
use App\Models\Rombel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RombelResource extends Resource
{
    protected static ?string $model = Rombel::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationGroup = 'Kesiswaan';
    protected static ?string $navigationLabel = 'Rombongan Belajar';
    protected static ?string $modelLabel = 'Rombel';
    protected static ?string $pluralModelLabel = 'Rombongan Belajar';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Data Rombel')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('nama_rombel')
                        ->label('Nama Rombel')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('Contoh: VII A 2024/2025'),

                    Forms\Components\Select::make('tahun_ajaran_id')
                        ->label('Tahun Ajaran')
                        ->relationship('tahunAjaran', 'nama')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\Select::make('kelas_id')
                        ->label('Kelas')
                        ->relationship('kelas', 'nama_kelas')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Forms\Components\TextInput::make('wali_kelas')
                        ->label('Wali Kelas')
                        ->maxLength(100),

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
                Tables\Columns\TextColumn::make('nama_rombel')
                    ->label('Nama Rombel')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('tahunAjaran.nama')
                    ->label('Tahun Ajaran')
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('kelas.nama_kelas')
                    ->label('Kelas')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('wali_kelas')
                    ->label('Wali Kelas')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('studentRombels_count')
                    ->label('Jml Siswa')
                    ->counts('studentRombels')
                    ->badge()
                    ->color('success'),

                Tables\Columns\IconColumn::make('is_aktif')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tahun_ajaran_id')
                    ->label('Tahun Ajaran')
                    ->relationship('tahunAjaran', 'nama'),

                Tables\Filters\SelectFilter::make('kelas_id')
                    ->label('Kelas')
                    ->relationship('kelas', 'nama_kelas'),

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
            ->defaultSort('nama_rombel')
            ->striped();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRombels::route('/'),
            'create' => Pages\CreateRombel::route('/create'),
            'edit'   => Pages\EditRombel::route('/{record}/edit'),
        ];
    }
}
