<?php

namespace App\Filament\Resources\Kesiswaan;

use App\Filament\Resources\Kesiswaan\StudentResource\Pages;
use App\Models\Kelas;
use App\Models\Student;
use App\Models\TahunAjaran;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Kesiswaan';
    protected static ?string $navigationLabel = 'Data Siswa';
    protected static ?string $modelLabel = 'Siswa';
    protected static ?string $pluralModelLabel = 'Data Siswa';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identitas Siswa')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Lengkap')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('nis')
                        ->label('NIS')
                        ->unique(ignoreRecord: true)
                        ->maxLength(20),

                    Forms\Components\TextInput::make('nisn')
                        ->label('NISN')
                        ->unique(ignoreRecord: true)
                        ->maxLength(20),

                    Forms\Components\DatePicker::make('birth_date')
                        ->label('Tanggal Lahir')
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    Forms\Components\TextInput::make('birth_place')
                        ->label('Tempat Lahir')
                        ->maxLength(100),

                    Forms\Components\Select::make('gender')
                        ->label('Jenis Kelamin')
                        ->options([
                            'L' => 'Laki-laki',
                            'P' => 'Perempuan',
                        ]),

                    Forms\Components\Select::make('religion')
                        ->label('Agama')
                        ->options([
                            'Islam'     => 'Islam',
                            'Kristen'   => 'Kristen',
                            'Katolik'   => 'Katolik',
                            'Hindu'     => 'Hindu',
                            'Buddha'    => 'Buddha',
                            'Konghucu'  => 'Konghucu',
                        ]),

                    Forms\Components\Select::make('kelas_id')
                        ->label('Kelas')
                        ->relationship('kelas', 'nama_kelas')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                ]),

            Forms\Components\Section::make('Status & Administrasi')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(Student::STATUSES)
                        ->default('active')
                        ->required(),

                    Forms\Components\TextInput::make('rfid_tag')
                        ->label('RFID Tag')
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),

                    Forms\Components\TextInput::make('phone')
                        ->label('No. HP Siswa')
                        ->tel()
                        ->maxLength(20),

                    Forms\Components\TextInput::make('parent_phone')
                        ->label('No. HP Orang Tua')
                        ->tel()
                        ->maxLength(20),

                    Forms\Components\TextInput::make('address')
                        ->label('Alamat')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Data Orang Tua')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('father_name')
                        ->label('Nama Ayah')
                        ->maxLength(150),

                    Forms\Components\TextInput::make('mother_name')
                        ->label('Nama Ibu')
                        ->maxLength(150),

                    Forms\Components\TextInput::make('guardian_name')
                        ->label('Nama Wali')
                        ->maxLength(150),

                    Forms\Components\TextInput::make('guardian_phone')
                        ->label('No. HP Wali')
                        ->tel()
                        ->maxLength(20),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nis')
                    ->label('NIS')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('kelas.nama_kelas')
                    ->label('Kelas')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('gender')
                    ->label('L/P')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'L' => 'info',
                        'P' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active'        => 'success',
                        'graduated'     => 'info',
                        'alumni'        => 'gray',
                        'pindah_keluar' => 'warning',
                        'keluar'        => 'danger',
                        default         => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => Student::STATUSES[$state] ?? $state),

                Tables\Columns\TextColumn::make('phone')
                    ->label('No. HP')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Student::STATUSES),

                Tables\Filters\SelectFilter::make('kelas_id')
                    ->label('Kelas')
                    ->relationship('kelas', 'nama_kelas')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('gender')
                    ->label('Jenis Kelamin')
                    ->options(['L' => 'Laki-laki', 'P' => 'Perempuan']),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('reset_password')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reset Password Siswa')
                    ->modalDescription(fn ($record) => 'Reset password ' . $record->name . ' ke tanggal lahir (' . \Carbon\Carbon::parse($record->birth_date)->format('dmy') . ')? Password custom akan dihapus.')
                    ->modalSubmitActionLabel('Ya, Reset Password')
                    ->action(function ($record) {
                        $record->update(['custom_password' => null]);
                        \Filament\Notifications\Notification::make()
                            ->title('Password berhasil direset')
                            ->body('Password ' . $record->name . ' kembali ke tanggal lahir (' . \Carbon\Carbon::parse($record->birth_date)->format('dmy') . ').')
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => ! is_null($record->custom_password)),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->striped();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'view'   => Pages\ViewStudent::route('/{record}'),
            'edit'   => Pages\EditStudent::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'active')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
