@extends('layouts.public')
@section('title','Reset Password - RSUD MGR GM')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
        <div class="max-w-lg mx-auto">
            <div class="bg-white rounded-2xl shadow-lg ring-1 ring-black/5 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100">
                    <h1 class="text-2xl font-semibold text-slate-800">Atur Ulang Password</h1>
                    <p class="text-slate-500 mt-1 text-sm">Silakan isi email dan password baru Anda.</p>
                </div>

                <div class="p-6">
                    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
                        @csrf

                        <input type="hidden" name="token" value="{{ request()->route('token') }}">

                        <div>
                            <label for="email" class="block text-[15px] font-medium text-slate-700">Email</label>
                            <x-ui.input id="email" name="email" type="email" :value="old('email', request()->email)" required autofocus autocomplete="username" />
                            @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="password" class="block text-[15px] font-medium text-slate-700">Password Baru</label>
                            <x-ui.input id="password" name="password" type="password" required autocomplete="new-password" />
                            @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-[15px] font-medium text-slate-700">Konfirmasi Password</label>
                            <x-ui.input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
                            @error('password_confirmation')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div class="flex items-center gap-3">
                            <x-ui.button type="submit" class="h-11">Reset Password</x-ui.button>
                            <x-ui.button as="a" href="{{ route('login') }}" variant="outline" class="h-11">Kembali ke Login</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
