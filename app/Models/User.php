<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    /**
     * Kolom yang boleh diisi manual (Mass Assignment)
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',   // Admin, TU, Student
        'phone',  // No HP untuk Notifikasi
        'avatar',  // Foto profil
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // RELASI: User (Orang Tua) punya banyak Siswa (Anak)
    public function students()
    {
        return $this->hasMany(Student::class, 'user_id');
    }
    
    // Helper untuk cek role lebih mudah: $user->hasRole('admin')
    public function hasRole($role): bool
    {
        return $this->role === $role;
    }

    /**
     * Filament: izinkan akses panel untuk seluruh role staf (bukan siswa).
     * Wajib di production — tanpa ini Filament menolak semua user (403).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['admin', 'tu', 'staf', 'kepala_sekolah'], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTu(): bool
    {
        return $this->role === 'tu';
    }

    public function isStaf(): bool
    {
        return $this->role === 'staf';
    }

    public function isKepalaSekolah(): bool
    {
        return $this->role === 'kepala_sekolah';
    }

    /**
     * Returns true if the user can perform write/financial operations.
     * Convenience helper — do not use as the sole authorization gate.
     */
    public function canManageFinance(): bool
    {
        return in_array($this->role, ['admin', 'tu', 'staf'], true);
    }

    /**
     * URL foto profil — fallback ke null kalau tidak ada.
     */
    public function getProfilePhotoUrlAttribute(): ?string
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        return null;
    }
}