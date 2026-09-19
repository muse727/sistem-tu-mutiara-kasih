{{--
    Student Portal — Bottom Navigation (app shell)
    Reused by dashboard / history / chat / profil.
    - Pembayaran → siswa.dashboard
    - Chat       → siswa.chat  (halaman penuh)
    - Profil     → siswa.profil
    Active: pill oranye lembut (#F56C3E bg-10% + ikon/teks oranye).
    Inactive: abu-abu mute.
--}}
@php
    $navActive = $active ?? '';
    $pillOn  = 'bg-[#F56C3E]/10 text-[#F56C3E]';
    $pillOff = 'text-[#777777] hover:text-[#333333] hover:bg-[#F5F5F5]';
@endphp

<nav aria-label="Navigasi utama" class="fixed bottom-0 left-0 right-0 z-50 no-print">
    <div class="bg-white border-t border-[#EDEDED] shadow-[0_-1px_6px_rgba(0,0,0,0.05)]">
        <div class="grid max-w-md grid-cols-3 gap-1 px-3 py-2 mx-auto" style="padding-bottom:calc(0.5rem + env(safe-area-inset-bottom));">

            {{-- PEMBAYARAN --}}
            <a href="{{ route('siswa.dashboard') }}"
               aria-label="Pembayaran" @if($navActive === 'pembayaran') aria-current="page" @endif
               class="flex flex-col items-center justify-center gap-1 px-2 py-2 rounded-2xl min-h-[56px] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#F56C3E]/50 {{ $navActive === 'pembayaran' ? $pillOn : $pillOff }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3"/>
                </svg>
                <span class="text-[11px] font-semibold leading-none">Pembayaran</span>
            </a>

            {{-- CHAT --}}
            <a href="{{ route('siswa.chat') }}"
               aria-label="Chat" @if($navActive === 'chat') aria-current="page" @endif
               class="relative flex flex-col items-center justify-center gap-1 px-2 py-2 rounded-2xl min-h-[56px] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#F56C3E]/50 {{ $navActive === 'chat' ? $pillOn : $pillOff }}">
                <span class="relative">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                    </svg>
                    {{-- unread badge — hanya dirender di halaman yang memuat state chat --}}
                    @if(isset($chatBadge) && $chatBadge)
                    <span x-show="chatUnread > 0" x-cloak
                          x-text="chatUnread"
                          class="absolute flex items-center justify-center w-4 h-4 text-[10px] font-bold text-white bg-red-500 rounded-full -top-1.5 -right-2"></span>
                    @endif
                </span>
                <span class="text-[11px] font-semibold leading-none">Chat</span>
            </a>

            {{-- PROFIL --}}
            <a href="{{ route('siswa.profil') }}"
               aria-label="Profil" @if($navActive === 'profil') aria-current="page" @endif
               class="flex flex-col items-center justify-center gap-1 px-2 py-2 rounded-2xl min-h-[56px] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#F56C3E]/50 {{ $navActive === 'profil' ? $pillOn : $pillOff }}">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                </svg>
                <span class="text-[11px] font-semibold leading-none">Profil</span>
            </a>

        </div>
    </div>
</nav>
