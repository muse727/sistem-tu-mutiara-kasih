<?php

namespace App\Filament\Resources\Pos;

use App\Filament\Resources\Pos\PosItemResource\Pages;
use App\Models\PosItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PosItemResource extends Resource
{
    protected static ?string $model = PosItem::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Point of Sale';
    protected static ?string $navigationLabel = 'Master Barang';
    protected static ?string $modelLabel = 'Barang';
    protected static ?string $pluralModelLabel = 'Master Barang';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informasi Barang')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Barang')
                        ->required()
                        ->maxLength(150),

                    Forms\Components\TextInput::make('sku')
                        ->label('SKU / Kode Barang')
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('price')
                        ->label('Harga Jual (Rp)')
                        ->numeric()
                        ->prefix('Rp')
                        ->required()
                        ->minValue(0),

                    Forms\Components\TextInput::make('stock')
                        ->label('Stok')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),

                    Forms\Components\Select::make('category')
                        ->label('Kategori')
                        ->options([
                            'makanan'   => 'Makanan',
                            'minuman'   => 'Minuman',
                            'alat_tulis'=> 'Alat Tulis',
                            'seragam'   => 'Seragam',
                            'lainnya'   => 'Lainnya',
                        ])
                        ->default('lainnya'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),

                    Forms\Components\Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock')
                    ->label('Stok')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state <= 0 ? 'danger' : ($state <= 5 ? 'warning' : 'success')),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Kategori')
                    ->options([
                        'makanan'    => 'Makanan',
                        'minuman'    => 'Minuman',
                        'alat_tulis' => 'Alat Tulis',
                        'seragam'    => 'Seragam',
                        'lainnya'    => 'Lainnya',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),

                Tables\Filters\Filter::make('low_stock')
                    ->label('Stok Menipis (≤5)')
                    ->query(fn ($query) => $query->where('stock', '<=', 5)),
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
            ->defaultSort('name')
            ->striped();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPosItems::route('/'),
            'create' => Pages\CreatePosItem::route('/create'),
            'edit'   => Pages\EditPosItem::route('/{record}/edit'),
        ];
    }
}
