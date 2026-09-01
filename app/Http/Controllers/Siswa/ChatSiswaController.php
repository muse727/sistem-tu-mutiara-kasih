<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatSiswaController extends Controller
{
    /**
     * Ambil semua pesan chat untuk siswa yang sedang login.
     * Sekaligus tandai pesan dari TU sebagai sudah dibaca.
     */
    public function index()
    {
        $student = Auth::guard('siswa')->user();

        // Tandai semua pesan dari TU sebagai read
        ChatMessage::where('student_id', $student->id)
            ->where('sender', 'tu')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = ChatMessage::where('student_id', $student->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($m) => [
                'id'         => $m->id,
                'sender'     => $m->sender,
                'message'    => $m->message,
                'created_at' => $m->created_at->format('H:i'),
                'date'       => $m->created_at->translatedFormat('d M Y'),
            ]);

        return response()->json([
            'messages' => $messages,
        ]);
    }

    /**
     * Siswa kirim pesan ke TU.
     */
    public function send(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $student = Auth::guard('siswa')->user();

        $chat = ChatMessage::create([
            'student_id' => $student->id,
            'sender'     => 'student',
            'message'    => $request->message,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $chat->id,
                'sender'     => $chat->sender,
                'message'    => $chat->message,
                'created_at' => $chat->created_at->format('H:i'),
                'date'       => $chat->created_at->translatedFormat('d M Y'),
            ],
        ]);
    }

    /**
     * Cek jumlah pesan belum dibaca dari TU (untuk badge polling).
     */
    public function unread()
    {
        $student = Auth::guard('siswa')->user();

        $count = ChatMessage::where('student_id', $student->id)
            ->where('sender', 'tu')
            ->whereNull('read_at')
            ->count();

        return response()->json(['unread' => $count]);
    }
}
