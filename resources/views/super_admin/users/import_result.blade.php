@extends('layouts.app')

@section('content')
<div class="container-px py-6 space-y-6 max-w-6xl mx-auto">
    <h1 class="text-2xl font-semibold text-slate-800">Hasil Impor Pengguna</h1>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">
                <div class="text-xs text-slate-500">Total baris</div>
                <div class="text-lg font-semibold text-slate-800">{{ $total_rows ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900">
                <div class="text-xs text-emerald-700">Berhasil</div>
                <div class="text-lg font-semibold">{{ $success_rows ?? 0 }}</div>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-900">
                <div class="text-xs text-rose-700">Gagal</div>
                <div class="text-lg font-semibold">{{ $failed_rows ?? 0 }}</div>
            </div>
        </div>

        @if(!empty($row_errors))
            <h2 class="text-base font-semibold text-slate-800 mt-6 mb-3">Daftar Error</h2>
            <div class="rounded-2xl border border-slate-200 bg-white">
                <div class="overflow-auto">
                    <table class="min-w-[900px] w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-4 py-3 text-left">Row</th>
                                <th class="px-4 py-3 text-left">Email</th>
                                <th class="px-4 py-3 text-left">Error</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        @foreach($row_errors as $e)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">{{ $e['row_number'] }}</td>
                                <td class="px-4 py-3 font-medium break-all">{{ $e['email'] }}</td>
                                <td class="px-4 py-3 text-rose-700">{{ $e['error_message'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Optional: detail semua baris (success + failed) --}}
        @if(!empty($results))
            <h2 class="text-base font-semibold text-slate-800 mt-6 mb-3">Ringkasan Per Baris</h2>
            <div class="rounded-2xl border border-slate-200 bg-white">
                <div class="overflow-auto">
                    <table class="min-w-[900px] w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-4 py-3 text-left">Row</th>
                                <th class="px-4 py-3 text-left">Email</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        @foreach($results as $r)
                            <tr class="hover:bg-slate-50 {{ ($r['status'] ?? '')==='failed' ? 'bg-rose-50/40' : '' }}">
                                <td class="px-4 py-3">{{ $r['row'] }}</td>
                                <td class="px-4 py-3 font-medium break-all">{{ $r['email'] }}</td>
                                <td class="px-4 py-3">
                                    @if(($r['status'] ?? '')==='ok')
                                        <span class="text-emerald-700 font-medium">OK</span>
                                    @else
                                        <span class="text-rose-600 font-medium">Gagal</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $r['reason'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <div class="flex justify-between">
        <a href="{{ route('super_admin.users.import.form') }}" class="text-sm text-slate-600 hover:text-slate-800">Impor Lagi</a>
        <a href="{{ route('super_admin.users.index') }}" class="btn-blue-grad text-sm font-medium px-5 py-2 rounded-lg">Ke Daftar Pengguna</a>
    </div>
</div>
@endsection
