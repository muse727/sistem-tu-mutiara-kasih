<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Chat TU — {{ $student->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        .chat-bubble-in  { background:#F5F5F5; border-radius:4px 18px 18px 18px; }
        .chat-bubble-out { background:#F56C3E; color:#fff; border-radius:18px 4px 18px 18px; }
        #chat-messages   { scroll-behavior: smooth; }
    </style>
</head>
<body class="flex flex-col h-screen pb-24 overflow-hidden bg-gray-50" x-data="chatWidget()">

    {{-- ===== HEADER ===== --}}
    <div class="bg-[#F56C3E] px-4 pt-10 pb-4 shrink-0">
        <div class="flex items-center max-w-md gap-3 mx-auto">
            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-white/20 shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-base font-bold leading-tight text-white">Hubungi TU</p>
                <p class="mt-0.5 text-xs text-white/80">Tata Usaha siap membantu</p>
            </div>
        </div>
    </div>

    {{-- ===== CHAT (partial bersama, mode halaman penuh — full-bleed di dalam shell) ===== --}}
    <div class="flex flex-col flex-1 w-full max-w-md min-h-0 mx-auto bg-white border-x border-[#EDEDED]">
        @include('siswa.partials.chat-widget', ['mode' => 'page'])
    </div>

    {{-- ===== BOTTOM NAVIGATION (app shell) ===== --}}
    @include('siswa.partials.bottom-nav', ['active' => 'chat'])

</body>
</html>
