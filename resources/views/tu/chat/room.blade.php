<!DOCTYPE html>
<html lang="id" x-data="chatRoom()" x-init="init()">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Chat — {{ $student->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] { display: none !important; }
        .chat-bubble-student {
            background: #F5F5F5;
            border-radius: 4px 18px 18px 18px;
        }
        .chat-bubble-tu {
            background: #F56C3E;
            color: white;
            border-radius: 18px 4px 18px 18px;
        }
        #chat-box { scroll-behavior: smooth; }
    </style>
</head>
<body class="bg-gray-100 h-screen flex flex-col">

    {{-- Header --}}
    <div class="bg-white border-b border-gray-200 px-4 py-3 flex items-center gap-3 shrink-0 shadow-sm">
        <a href="{{ route('chat.inbox') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
        <div class="w-9 h-9 rounded-xl bg-sky-100 flex items-center justify-center text-sky-600 font-bold text-sm">
            {{ strtoupper(substr($student->name, 0, 1)) }}
        </div>
        <div>
            <p class="font-semibold text-gray-800 text-sm leading-tight">{{ $student->name }}</p>
            <p class="text-xs text-gray-400">NIS: {{ $student->nis }} · {{ optional($student->kelas)->nama_kelas ?? '-' }}</p>
        </div>
    </div>

    {{-- Chat Box --}}
    <div id="chat-box" class="flex-1 overflow-y-auto px-4 py-4 space-y-3">

        {{-- Pesan awal dari DB (server-side render) --}}
        @php $lastDate = null; @endphp
        @foreach($messages as $msg)
            @if($lastDate !== $msg->created_at->format('Y-m-d'))
                @php $lastDate = $msg->created_at->format('Y-m-d') @endphp
                <div class="flex justify-center">
                    <span class="bg-gray-200 text-gray-500 text-xs px-3 py-1 rounded-full">
                        {{ $msg->created_at->translatedFormat('d M Y') }}
                    </span>
                </div>
            @endif

            @if($msg->sender === 'student')
                <div class="flex items-end gap-2">
                    <div class="w-7 h-7 rounded-full bg-sky-100 flex items-center justify-center text-sky-600 font-bold text-xs shrink-0">
                        {{ strtoupper(substr($student->name, 0, 1)) }}
                    </div>
                    <div class="max-w-[75%]">
                        <div class="chat-bubble-student px-4 py-2.5 text-sm text-gray-800">{{ $msg->message }}</div>
                        <p class="text-xs text-gray-400 mt-1 ml-1">{{ $msg->created_at->format('H:i') }}</p>
                    </div>
                </div>
            @else
                <div class="flex items-end justify-end gap-2">
                    <div class="max-w-[75%] text-right">
                        <div class="chat-bubble-tu px-4 py-2.5 text-sm inline-block">{{ $msg->message }}</div>
                        <p class="text-xs text-gray-400 mt-1 mr-1">{{ $msg->created_at->format('H:i') }} · TU</p>
                    </div>
                </div>
            @endif
        @endforeach

        {{-- Pesan baru via Alpine (dari polling) --}}
        <template x-for="msg in newMessages" :key="msg.id">
            <div>
                <template x-if="msg.sender === 'student'">
                    <div class="flex items-end gap-2">
                        <div class="w-7 h-7 rounded-full bg-sky-100 flex items-center justify-center text-sky-600 font-bold text-xs shrink-0">
                            {{ strtoupper(substr($student->name, 0, 1)) }}
                        </div>
                        <div class="max-w-[75%]">
                            <div class="chat-bubble-student px-4 py-2.5 text-sm text-gray-800" x-text="msg.message"></div>
                            <p class="text-xs text-gray-400 mt-1 ml-1" x-text="msg.created_at"></p>
                        </div>
                    </div>
                </template>
                <template x-if="msg.sender === 'tu'">
                    <div class="flex items-end justify-end gap-2">
                        <div class="max-w-[75%] text-right">
                            <div class="chat-bubble-tu px-4 py-2.5 text-sm inline-block" x-text="msg.message"></div>
                            <p class="text-xs text-gray-400 mt-1 mr-1" x-text="msg.created_at + ' · TU'"></p>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        {{-- Loading indicator --}}
        <div x-show="sending" x-cloak class="flex justify-end">
            <div class="bg-sky-300 text-white text-xs px-4 py-2 rounded-full animate-pulse">Mengirim...</div>
        </div>
    </div>

    {{-- Input Box --}}
    <div class="bg-white border-t border-gray-200 px-4 py-3 shrink-0">
        <form @submit.prevent="sendMessage" class="flex items-end gap-2">
            <textarea
                x-model="replyText"
                @keydown.enter.prevent="!$event.shiftKey && sendMessage()"
                placeholder="Ketik balasan... (Enter untuk kirim)"
                rows="1"
                class="flex-1 resize-none rounded-2xl border border-gray-200 px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400 transition-colors"
                :disabled="sending"
            ></textarea>
            <button
                type="submit"
                :disabled="!replyText.trim() || sending"
                class="w-10 h-10 bg-sky-500 hover:bg-sky-600 disabled:bg-gray-200 text-white rounded-2xl flex items-center justify-center transition-colors shrink-0"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.269 20.876L5.999 12zm0 0h7.5"/>
                </svg>
            </button>
        </form>
        <p class="text-xs text-gray-400 mt-1 ml-1">Shift+Enter untuk baris baru</p>
    </div>

    <script>
        function chatRoom() {
            return {
                replyText: '',
                sending: false,
                newMessages: [],
                lastId: {{ $messages->last()?->id ?? 0 }},
                pollInterval: null,

                init() {
                    this.scrollToBottom();
                    // Polling setiap 4 detik
                    this.pollInterval = setInterval(() => this.pollMessages(), 4000);
                },

                scrollToBottom() {
                    this.$nextTick(() => {
                        const box = document.getElementById('chat-box');
                        if (box) box.scrollTop = box.scrollHeight;
                    });
                },

                async sendMessage() {
                    const text = this.replyText.trim();
                    if (!text || this.sending) return;

                    this.sending = true;
                    this.replyText = '';

                    try {
                        const res = await fetch('/chat/{{ $student->id }}/reply', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ message: text }),
                        });

                        const data = await res.json();
                        if (data.success) {
                            this.newMessages.push(data.data);
                            this.lastId = data.data.id;
                            this.scrollToBottom();
                        }
                    } catch (e) {
                        alert('Gagal mengirim pesan. Coba lagi.');
                        this.replyText = text;
                    } finally {
                        this.sending = false;
                    }
                },

                async pollMessages() {
                    try {
                        const res = await fetch(`/chat/{{ $student->id }}/poll?after_id=${this.lastId}`);
                        const data = await res.json();
                        if (data.messages && data.messages.length > 0) {
                            // Filter jangan duplikat (kalau TU sendiri yang kirim, sudah masuk via sendMessage)
                            const existingIds = new Set(this.newMessages.map(m => m.id));
                            const fresh = data.messages.filter(m => !existingIds.has(m.id));
                            if (fresh.length > 0) {
                                this.newMessages.push(...fresh);
                                this.lastId = fresh[fresh.length - 1].id;
                                this.scrollToBottom();
                            }
                        }
                    } catch (e) {}
                }
            }
        }
    </script>

</body>
</html>
