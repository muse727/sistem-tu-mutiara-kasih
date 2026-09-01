<?php

namespace App\Filament\Resources\Pos;

use App\Filament\Resources\Pos\PosBundleResource\Pages;
use App\Models\PosBundle;
use App\Models\PosItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PosBundleResource extends Resource
{
    protected static ?string $model = PosBundle::class;
    protected static ?string $navigationIcon = 'heroicon-o-squares-plus';
    protected static ?string $navigationGroup = 'Point of Sale';
    protected static ?string $navigationLabel = 'Paket / Bundle';
    protected static ?string $modelLabel = 'Paket';
    protected static ?string $pluralModelLabel = 'Paket Bundling';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informasi Paket')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Paket')
                        ->required()
                        ->maxLength(150),

                    Forms\Components\TextInput::make('price')
                        ->label('Harga Paket (Rp)')
                        ->numeric()
                        ->prefix('Rp')
                        ->default(0)
                        ->minValue(0),

                    Forms\Components\Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),
                ]),

            Forms\Components\Section::make('Isi Paket')
                ->schema([
                    Forms\Components\Repeater::make('bundleItems')
                        ->label('Barang dalam Paket')
                        ->relationship('bundleItems')
                        ->schema([
                            Forms\Components\Select::make('pos_item_id')
                                ->label('Barang')
                                ->options(PosItem::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->required(),

                            Forms\Components\TextInput::make('quantity')
                                ->label('Qty')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->required(),
                        ])
                        ->columns(2)
                        ->addActionLabel('+ Tambah Barang')
                        ->defaultItems(1),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Paket')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('bundleItems_count')
                    ->label('Jml Item')
                    ->counts('bundleItems')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Status Aktif'),
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
            'index'  => Pages\ListPosBundles::route('/'),
            'create' => Pages\CreatePosBundle::route('/create'),
            'edit'   => Pages\EditPosBundle::route('/{record}/edit'),
        ];
    }
}
