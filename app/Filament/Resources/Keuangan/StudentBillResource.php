<?php

namespace App\Filament\Resources\Keuangan;

use App\Filament\Resources\Keuangan\StudentBillResource\Pages;
use App\Models\Student;
use App\Models\StudentBill;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Repeater;

class StudentBillResource extends Resource
{
    protected static ?string $model = StudentBill::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';
    protected static ?string $navigationGroup = 'Keuangan';
    protected static ?string $navigationLabel = 'Tagihan Siswa';
    protected static ?string $modelLabel = 'Tagihan';
    protected static ?string $pluralModelLabel = 'Tagihan Siswa';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Data Tagihan')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('student_id')
                        ->label('Siswa')
                        ->relationship('student', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->getOptionLabelFromRecordUsing(fn ($record) => "[{$record->nis}] {$record->name}"),

                    Forms\Components\Select::make('type')
                        ->label('Jenis Tagihan')
                        ->options([
                            'spp'           => 'SPP Bulanan',
                            'uang_gedung'   => 'Uang Gedung',
                            'seragam'       => 'Seragam',
                            'ekstra'        => 'Ekstrakurikuler',
                            'lainnya'       => 'Lainnya',
                        ])
                        ->required(),

                    Forms\Components\TextInput::make('description')
                        ->label('Keterangan')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('amount')
                        ->label('Jumlah Tagihan (Rp)')
                        ->numeric()
                        ->prefix('Rp')
                        ->required()
                        ->minValue(0),

                    Forms\Components\TextInput::make('discount')
                        ->label('Diskon (Rp)')
                        ->numeric()
                        ->prefix('Rp')
                        ->default(0)
                        ->minValue(0),

                    Forms\Components\DatePicker::make('due_date')
                        ->label('Jatuh Tempo')
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'UNPAID'  => 'Belum Bayar',
                            'PARTIAL' => 'Dibayar Sebagian',
                            'PAID'    => 'Lunas',
                        ])
                        ->default('UNPAID')
                        ->required(),

                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label('Dibayar Pada')
                        ->native(false)
                        ->nullable()
                        ->visible(fn ($get) => in_array($get('status'), ['PAID', 'PARTIAL'])),

                    Forms\Components\Select::make('payment_method')
                        ->label('Metode Pembayaran')
                        ->options([
                            'cash'     => 'Tunai',
                            'transfer' => 'Transfer Bank',
                            'qris'     => 'QRIS',
                            'midtrans' => 'Midtrans',
                        ])
                        ->nullable(),

                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Rincian Tagihan')
                ->description('Opsional — isi jika tagihan memiliki breakdown item. Jika diisi, total otomatis dihitung dari rincian.')
                ->collapsible()
                ->schema([
                    Repeater::make('items')
                        ->label('')
                        ->relationship('items')
                        ->schema([
                            Forms\Components\TextInput::make('item_name')
                                ->label('Nama Item')
                                ->required()
                                ->placeholder('Contoh: Biaya Gedung, SPP, Seragam...')
                                ->columnSpan(3),

                            Forms\Components\TextInput::make('quantity')
                                ->label('Qty')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn ($state, $set, $get) =>
                                    $set('subtotal', (float) $state * (float) ($get('price') ?? 0))
                                )
                                ->columnSpan(1),

                            Forms\Components\TextInput::make('price')
                                ->label('Harga Satuan (Rp)')
                                ->numeric()
                                ->prefix('Rp')
                                ->required()
                                ->minValue(0)
                                ->live()
                                ->afterStateUpdated(fn ($state, $set, $get) =>
                                    $set('subtotal', (float) $state * (float) ($get('quantity') ?? 1))
                                )
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('subtotal')
                                ->label('Subtotal (Rp)')
                                ->numeric()
                                ->prefix('Rp')
                                ->readOnly()
                                ->default(0)
                                ->columnSpan(2),
                        ])
                        ->columns(8)
                        ->addActionLabel('+ Tambah Item Rincian')
                        ->reorderable()
                        ->cloneable()
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.nis')
                    ->label('NIS')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('student.name')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('student.kelas.nama_kelas')
                    ->label('Kelas')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Keterangan')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Tagihan')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
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
                        'PARTIAL' => 'Sebagian',
                        'UNPAID'  => 'Belum Bayar',
                        default   => $state,
                    }),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($record) => $record->status === 'UNPAID' && $record->due_date?->isPast() ? 'danger' : null),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Dibayar')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Pembayaran')
                    ->options([
                        'UNPAID'  => 'Belum Bayar',
                        'PARTIAL' => 'Dibayar Sebagian',
                        'PAID'    => 'Lunas',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Jenis Tagihan')
                    ->options([
                        'spp'           => 'SPP Bulanan',
                        'uang_gedung'   => 'Uang Gedung',
                        'seragam'       => 'Seragam',
                        'ekstra'        => 'Ekstrakurikuler',
                        'lainnya'       => 'Lainnya',
                    ]),

                Tables\Filters\Filter::make('overdue')
                    ->label('Jatuh Tempo')
                    ->query(fn (Builder $query) => $query->where('status', 'UNPAID')->whereDate('due_date', '<', now())),

                Tables\Filters\Filter::make('this_month')
                    ->label('Bulan Ini')
                    ->query(fn (Builder $query) => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)),
            ])
            ->actions([
                Tables\Actions\Action::make('bayar')
                    ->label('Bayar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status !== 'PAID')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Pembayaran')
                    ->modalDescription(fn ($record) => "Tandai tagihan \"{$record->description}\" senilai " . number_format($record->amount, 0, ',', '.') . " sebagai LUNAS?")
                    ->action(function ($record) {
                        $record->update([
                            'status'         => 'PAID',
                            'paid_at'        => now(),
                            'payment_method' => 'cash',
                            'confirmed_by'   => Auth::id(),
                        ]);
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStudentBills::route('/'),
            'create' => Pages\CreateStudentBill::route('/create'),
            'view'   => Pages\ViewStudentBill::route('/{record}'),
            'edit'   => Pages\EditStudentBill::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'UNPAID')
            ->whereDate('due_date', '<', now())
            ->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
