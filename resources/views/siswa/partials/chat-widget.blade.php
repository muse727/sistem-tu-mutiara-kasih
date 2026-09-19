{{--
    Student Portal — Chat (SATU-SATUNYA implementasi, dipakai ulang).
    mode = 'panel' → tersembunyi sampai chatOpen=true (toggle dari luar; dashboard).
    mode = 'page'  → selalu terbuka sebagai halaman penuh (/siswa/chat).
    State & perilaku (unread polling, load, polling pesan, kirim, scroll)
    berasal dari chatWidget() yang sama — TIDAK diduplikasi.
--}}
@php
    $chatMode = $mode ?? 'panel';
    $isPage = $chatMode === 'page';
@endphp

@if($isPage)
    {{-- ===== FULL-PAGE (full-bleed, bagian dari app shell — TANPA kartu melayang) ===== --}}
    <div class="flex flex-col flex-1 w-full min-h-0 bg-white">
@else
    {{-- ===== PANEL (dashboard) ===== --}}
    <div id="chat-panel-wrapper"
         style="position:fixed; right:16px; z-index:50; bottom:calc(80px + env(safe-area-inset-bottom));"
         x-show="chatOpen" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-4">
    <div class="flex flex-col overflow-hidden bg-white border border-gray-100 shadow-2xl w-80 max-w-[calc(100vw-2rem)] rounded-3xl"
         style="height:420px; max-height:calc(100vh - 160px);">
@endif

        {{-- Header (hanya untuk mode panel; halaman penuh punya header sendiri) --}}
        @if(!$isPage)
        <div class="bg-gradient-to-r from-[#F56C3E] to-[#e45322] px-4 py-3 flex items-center gap-2 shrink-0">
            <div class="flex items-center justify-center w-8 h-8 rounded-xl bg-white/20">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold leading-tight text-white">Hubungi TU</p>
                <p class="text-xs text-white/80">Tata Usaha siap membantu</p>
            </div>
        </div>
        @endif

        {{-- Messages area --}}
        <div id="chat-messages" class="flex-1 px-3 py-3 space-y-2 overflow-y-auto">
            {{-- Loading state --}}
            <template x-if="chatLoading">
                <div class="flex justify-center py-4">
                    <div class="w-5 h-5 border-2 border-[#F56C3E] border-t-transparent rounded-full animate-spin"></div>
                </div>
            </template>

            {{-- Empty state --}}
            <template x-if="!chatLoading && chatMessages.length === 0">
                <div class="flex flex-col items-center justify-center h-full py-8 text-center">
                    <svg class="w-10 h-10 mb-2 text-gray-200" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                    </svg>
                    <p class="text-xs text-gray-400">Belum ada pesan.</p>
                    <p class="text-xs text-gray-400">Mulai chat dengan TU di sini.</p>
                </div>
            </template>

            {{-- Pesan --}}
            <template x-for="(msg, index) in chatMessages" :key="msg.id">
                <div>
                    {{-- Pemisah tanggal --}}
                    <template x-if="index === 0 || chatMessages[index-1].date !== msg.date">
                        <div class="flex justify-center my-2">
                            <span class="bg-gray-100 text-gray-400 text-xs px-2 py-0.5 rounded-full" x-text="msg.date"></span>
                        </div>
                    </template>

                    {{-- Pesan dari siswa --}}
                    <template x-if="msg.sender === 'student'">
                        <div class="flex justify-end">
                            <div class="max-w-[80%]">
                                <div class="px-3 py-2 text-xs chat-bubble-out" x-text="msg.message"></div>
                                <p class="text-xs text-gray-400 mt-0.5 text-right" x-text="msg.created_at"></p>
                            </div>
                        </div>
                    </template>

                    {{-- Pesan dari TU --}}
                    <template x-if="msg.sender === 'tu'">
                        <div class="flex justify-start">
                            <div class="max-w-[80%]">
                                <p class="text-xs text-gray-400 mb-0.5 ml-1">TU</p>
                                <div class="px-3 py-2 text-xs text-gray-800 chat-bubble-in" x-text="msg.message"></div>
                                <p class="text-xs text-gray-400 mt-0.5 ml-1" x-text="msg.created_at"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- Input --}}
        <div class="px-3 py-2 border-t border-gray-100 shrink-0" @if($isPage) style="padding-bottom:calc(0.5rem + env(safe-area-inset-bottom));" @endif>
            <form @submit.prevent="chatSend" class="flex items-end gap-2">
                <textarea
                    x-model="chatText"
                    @keydown.enter.prevent="!$event.shiftKey && chatSend()"
                    placeholder="Ketik pesan..."
                    rows="1"
                    class="flex-1 resize-none text-xs rounded-xl border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#F56C3E] focus:ring-1 focus:ring-[#F56C3E] transition-colors"
                    :disabled="chatSending"
                ></textarea>
                <button
                    type="submit"
                    :disabled="!chatText.trim() || chatSending"
                    class="w-8 h-8 bg-[#F56C3E]/100 hover:bg-[#F56C3E] disabled:bg-gray-200 text-white rounded-xl flex items-center justify-center transition-colors shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.269 20.876L5.999 12zm0 0h7.5"/>
                    </svg>
                </button>
            </form>
        </div>

    </div>
@if(!$isPage)
    </div>
@endif

{{-- ===== chatWidget() — definisi tunggal, hanya dirender sekali oleh halaman yang memuat chat ===== --}}
<script>
    function chatWidget() {
        return {
            chatOpen: {{ $isPage ? 'true' : 'false' }},
            chatLoading: false,
            chatSending: false,
            chatMessages: [],
            chatText: '',
            chatUnread: 0,
            chatLastId: 0,
            _chatPollInterval: null,
            _chatUnreadInterval: null,

            // Alpine lifecycle — dipanggil otomatis saat component init
            init() {
                @if($isPage)
                // Mode halaman: selalu terbuka.
                this.chatOpen = true;
                @else
                // Mode panel: selalu mulai tertutup.
                this.chatOpen = false;
                @endif

                // Polling unread badge setiap 10 detik (saat chat tertutup)
                this._chatUnreadInterval = setInterval(async () => {
                    if (this.chatOpen) return;
                    try {
                        const r = await fetch('{{ route("siswa.chat.unread") }}');
                        const d = await r.json();
                        this.chatUnread = d.unread ?? 0;
                    } catch (e) {}
                }, 10000);

                // Watch: saat chat dibuka, load pesan & mulai polling
                this.$watch('chatOpen', async (open) => {
                    if (open) {
                        await this.chatLoad();
                        this.chatUnread = 0;
                        this._chatPollInterval = setInterval(() => this.chatPoll(), 4000);
                    } else {
                        clearInterval(this._chatPollInterval);
                    }
                });

                @if($isPage)
                // Mode halaman langsung terbuka → trigger load awal.
                this.$nextTick(() => { this.chatOpen = false; this.chatOpen = true; });
                @endif
            },

            async chatLoad() {
                this.chatLoading = true;
                try {
                    const r = await fetch('{{ route("siswa.chat.messages") }}');
                    const d = await r.json();
                    this.chatMessages = d.messages ?? [];
                    if (this.chatMessages.length > 0) {
                        this.chatLastId = this.chatMessages[this.chatMessages.length - 1].id;
                    }
                    this.chatScrollBottom();
                } catch (e) {}
                this.chatLoading = false;
            },

            async chatPoll() {
                try {
                    const r = await fetch('{{ route("siswa.chat.messages") }}');
                    const d = await r.json();
                    const msgs = d.messages ?? [];
                    const existingIds = new Set(this.chatMessages.map(m => m.id));
                    const fresh = msgs.filter(m => !existingIds.has(m.id));
                    if (fresh.length > 0) {
                        this.chatMessages.push(...fresh);
                        this.chatLastId = fresh[fresh.length - 1].id;
                        this.chatScrollBottom();
                    }
                } catch (e) {}
            },

            async chatSend() {
                const text = this.chatText.trim();
                if (!text || this.chatSending) return;
                this.chatSending = true;
                this.chatText = '';
                try {
                    const r = await fetch('{{ route("siswa.chat.send") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ message: text }),
                    });
                    const d = await r.json();
                    if (d.success) {
                        this.chatMessages.push(d.data);
                        this.chatLastId = d.data.id;
                        this.chatScrollBottom();
                    }
                } catch (e) {
                    alert('Gagal mengirim pesan.');
                    this.chatText = text;
                } finally {
                    this.chatSending = false;
                }
            },

            chatScrollBottom() {
                this.$nextTick(() => {
                    const el = document.getElementById('chat-messages');
                    if (el) el.scrollTop = el.scrollHeight;
                });
            },
        };
    }
</script>
