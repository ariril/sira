@extends('layouts.public')
@section('title','Lupa Password - RSUD MGR GM')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
        <div class="max-w-lg mx-auto">
            <div class="bg-white rounded-2xl shadow-lg ring-1 ring-black/5 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100">
                    <h1 class="text-2xl font-semibold text-slate-800">Lupa Password</h1>
                    <p class="text-slate-500 mt-1 text-sm">Masukkan email Anda. Kami akan mengirim tautan untuk mengatur ulang password.</p>
                </div>

                <div class="p-6">
                    @if (session('status'))
                        <div class="mb-4 rounded-lg bg-green-50 text-green-700 px-4 py-3 text-sm">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label for="email" class="block text-[15px] font-medium text-slate-700">Email</label>
                            <x-ui.input id="email" name="email" type="email" :value="old('email')" required autofocus />
                        </div>

                        <div class="flex items-center gap-3">
                            <x-ui.button type="submit" class="h-11">Kirim Link Reset Password</x-ui.button>
                            <x-ui.button as="a" href="{{ route('login') }}" variant="outline" class="h-11">Kembali ke Login</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
