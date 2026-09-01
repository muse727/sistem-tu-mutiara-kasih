<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatMessage extends Model
{
    use HasFactory;

    protected $table = 'chat_messages';
    protected $guarded = ['id'];

    protected $casts = [
        'read_at'    => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ==========================================
    // RELASI
    // ==========================================

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    // ==========================================
    // HELPERS
    // ==========================================

    public function getIsFromStudentAttribute(): bool
    {
        return $this->sender === 'student';
    }

    public function getIsFromTuAttribute(): bool
    {
        return $this->sender === 'tu';
    }

    public function getIsReadAttribute(): bool
    {
        return $this->read_at !== null;
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeFromStudent($query)
    {
        return $query->where('sender', 'student');
    }

    public function scopeFromTu($query)
    {
        return $query->where('sender', 'tu');
    }
}
