<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Inbox Chat — TU</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

    {{-- Header --}}
    <div class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <h1 class="text-lg font-bold text-gray-800">Inbox Chat Siswa</h1>
            @if($totalUnread > 0)
                <span class="bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                    {{ $totalUnread }}
                </span>
            @endif
        </div>
        <p class="text-xs text-gray-400">{{ now()->translatedFormat('d M Y, H:i') }}</p>
    </div>

    <div class="max-w-2xl mx-auto px-4 py-6">

        @if($threads->isEmpty())
            <div class="text-center py-20">
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                </svg>
                <p class="text-gray-400 text-sm">Belum ada chat masuk dari siswa.</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach($threads as $thread)
                    <a href="{{ route('chat.room', $thread['student_id']) }}"
                       class="flex items-center gap-4 bg-white rounded-2xl px-4 py-3 border border-gray-100 hover:border-gray-200 hover:shadow-sm transition-all">

                        {{-- Avatar --}}
                        <div class="w-11 h-11 rounded-2xl bg-sky-100 flex items-center justify-center text-sky-600 font-bold text-base shrink-0">
                            {{ strtoupper(substr($thread['student_name'], 0, 1)) }}
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-semibold text-gray-800 text-sm truncate">{{ $thread['student_name'] }}</p>
                                <p class="text-xs text-gray-400 shrink-0">{{ $thread['last_time'] }}</p>
                            </div>
                            <div class="flex items-center justify-between gap-2 mt-0.5">
                                <p class="text-xs text-gray-500 truncate">{{ $thread['last_message'] }}</p>
                                @if($thread['unread'] > 0)
                                    <span class="bg-sky-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full shrink-0 min-w-[20px] text-center">
                                        {{ $thread['unread'] }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-400 mt-0.5">NIS: {{ $thread['student_nis'] }}</p>
                        </div>

                        <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Auto-refresh badge unread setiap 10 detik --}}
    <script>
        setInterval(async () => {
            try {
                const res = await fetch('/chat/unread-count');
                const data = await res.json();
                // Reload halaman kalau ada perubahan unread (simpel)
                document.title = data.unread > 0
                    ? `(${data.unread}) Inbox Chat — TU`
                    : 'Inbox Chat — TU';
            } catch (e) {}
        }, 10000);
    </script>

</body>
</html>
