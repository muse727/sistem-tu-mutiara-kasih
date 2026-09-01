<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Student;
use Illuminate\Http\Request;

class ChatTuController extends Controller
{
    /**
     * Inbox semua siswa yang pernah chat — untuk panel TU.
     */
    public function inbox()
    {
        // Ambil 1 pesan terakhir per siswa, urutkan dari yang paling baru
        $threads = ChatMessage::with('student')
            ->selectRaw('student_id, MAX(id) as last_message_id')
            ->groupBy('student_id')
            ->orderByDesc('last_message_id')
            ->get()
            ->map(function ($row) {
                $lastMsg  = ChatMessage::find($row->last_message_id);
                $unread   = ChatMessage::where('student_id', $row->student_id)
                                ->where('sender', 'student')
                                ->whereNull('read_at')
                                ->count();

                return [
                    'student_id'   => $row->student_id,
                    'student_name' => optional($lastMsg->student)->name ?? '-',
                    'student_nis'  => optional($lastMsg->student)->nis ?? '-',
                    'last_message' => $lastMsg->message,
                    'last_time'    => $lastMsg->created_at->diffForHumans(),
                    'unread'       => $unread,
                ];
            });

        $totalUnread = ChatMessage::where('sender', 'student')
            ->whereNull('read_at')
            ->count();

        // AJAX request dari floating widget → return JSON
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'threads'      => $threads->values(),
                'totalUnread'  => $totalUnread,
            ]);
        }

        return view('tu.chat.inbox', compact('threads', 'totalUnread'));
    }

    /**
     * Buka room chat dengan satu siswa.
     */
    public function room(Student $student)
    {
        // Tandai semua pesan dari siswa ini sebagai sudah dibaca
        ChatMessage::where('student_id', $student->id)
            ->where('sender', 'student')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = ChatMessage::where('student_id', $student->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return view('tu.chat.room', compact('student', 'messages'));
    }

    /**
     * TU balas pesan siswa (AJAX).
     */
    public function reply(Request $request, Student $student)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $chat = ChatMessage::create([
            'student_id' => $student->id,
            'sender'     => 'tu',
            'message'    => $request->message,
        ]);

        return response()->json([
            'success' => true,
            'msg'     => [
                'id'         => $chat->id,
                'sender'     => $chat->sender,
                'message'    => $chat->message,
                'created_at' => $chat->created_at->format('H:i'),
            ],
        ]);
    }

    /**
     * Polling pesan baru di room tertentu (untuk auto-refresh TU).
     */
    public function poll(Request $request, Student $student)
    {
        $afterId = $request->query('after_id', 0);

        $messages = ChatMessage::where('student_id', $student->id)
            ->where('id', '>', $afterId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($m) => [
                'id'         => $m->id,
                'sender'     => $m->sender,
                'message'    => $m->message,
                'created_at' => $m->created_at->format('H:i'),
            ]);

        // Tandai sebagai read
        ChatMessage::where('student_id', $student->id)
            ->where('sender', 'student')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['messages' => $messages]);
    }

    /**
     * Total unread badge untuk header/sidebar TU (AJAX polling).
     */
    public function unreadCount()
    {
        $count = ChatMessage::where('sender', 'student')
            ->whereNull('read_at')
            ->count();

        return response()->json(['unread' => $count]);
    }
}
