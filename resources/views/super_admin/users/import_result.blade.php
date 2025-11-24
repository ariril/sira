@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto">
    <h1 class="text-2xl font-semibold mb-6">Hasil Impor Pengguna</h1>

    <div class="bg-white rounded-2xl shadow p-6 border border-slate-200 mb-6">
        <p class="text-sm mb-2">Berhasil: <span class="font-medium text-green-600">{{ $count_ok }}</span> | Dilewati: <span class="font-medium text-amber-600">{{ $count_skip }}</span></p>
        <table class="w-full text-xs">
            <thead>
                <tr class="text-left border-b">
                    <th class="py-2">Baris</th>
                    <th class="py-2">Email</th>
                    <th class="py-2">Nama</th>
                    <th class="py-2">Roles (CSV)</th>
                    <th class="py-2">Profesi ID</th>
                    <th class="py-2">NIP</th>
                    <th class="py-2">Unit ID</th>
                    <th class="py-2">Status</th>
                    <th class="py-2">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($results as $r)
                    <tr class="border-b last:border-0 {{ $r['status']==='skip' ? 'bg-red-50' : '' }}">
                        <td class="py-1">{{ $r['row'] }}</td>
                        <td class="py-1 font-medium break-all">{{ $r['email'] }}</td>
                        <td class="py-1">{{ $r['raw']['name'] ?? '-' }}</td>
                        <td class="py-1 text-[11px]">{{ $r['raw']['roles'] ?? '-' }}</td>
                        <td class="py-1">{{ $r['raw']['profession_id'] ?? '-' }}</td>
                        <td class="py-1">{{ $r['raw']['employee_number'] ?? '-' }}</td>
                        <td class="py-1">{{ $r['raw']['unit_id'] ?? '-' }}</td>
                        <td class="py-1">
                            @if($r['status']==='ok')
                                <span class="text-green-600 font-medium">Ditambahkan</span>
                            @else
                                <span class="text-amber-600 font-medium">Dilewati</span>
                            @endif
                        </td>
                        <td class="py-1">{{ $r['reason'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-5 flex justify-between">
            <a href="{{ route('super_admin.users.import.form') }}" class="text-sm text-slate-600 hover:text-slate-800">Impor Lagi</a>
            <a href="{{ route('super_admin.users.index') }}" class="btn-blue-grad text-sm font-medium px-5 py-2 rounded-lg">Ke Daftar Pengguna</a>
        </div>
    </div>
</div>
@endsection
