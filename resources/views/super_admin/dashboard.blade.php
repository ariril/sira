@extends('layouts.app')

@section('content')
    <div class="container-px py-6">
        <h1 class="text-2xl font-semibold mb-4">Dashboard Super Admin</h1>

        {{-- cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-card metric="{{$stats['total_user']}}" label="Total User" />
            <x-card metric="{{$stats['total_unit']}}" label="Unit Kerja" />
            <x-card metric="{{$stats['total_profesi']}}" label="Profesi" />
            <x-card metric="{{$stats['unverified']}}" label="Email Belum Verifikasi" />
        </div>

        {{-- review summary --}}
        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="col-span-1 lg:col-span-1 p-4 rounded-xl border">
                <h2 class="font-semibold mb-2">Rating 30 Hari</h2>
                <p class="text-3xl font-bold">
                    {{ number_format($review['avg_rating_30d'] ?? 0, 2) }}
                    <span class="text-sm text-gray-500">({{ $review['total_30d'] }} ulasan)</span>
                </p>
            </div>

            <div class="col-span-1 lg:col-span-2 p-4 rounded-xl border">
                <h2 class="font-semibold mb-3">Top Tenaga Medis</h2>
                <div class="space-y-2">
                    @foreach($review['top_tenaga_medis'] as $row)
                        <div class="flex justify-between border-b pb-2">
                            <div>
                                <div class="font-medium">{{$row->nama}}</div>
                                <div class="text-xs text-gray-500">{{$row->jabatan}}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold">{{ number_format($row->avg_rating,2) }}</div>
                                <div class="text-xs text-gray-500">{{$row->total_ulasan}} ulasan</div>
                            </div>
                        </div>
                    @endforeach
                    @if($review['top_tenaga_medis']->isEmpty())
                        <div class="text-sm text-gray-500">Belum ada data ulasan.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- periode/remunerasi --}}
        <div class="mt-6 p-4 rounded-xl border">
            <h2 class="font-semibold mb-2">Periode & Remunerasi</h2>
            @if($kinerja['periode_aktif'])
                <p class="text-sm">Periode aktif: <span class="font-medium">#{{$kinerja['periode_aktif']->id}}</span></p>
                <p class="text-sm">Total remunerasi periode: <span class="font-medium">
        {{ number_format($kinerja['total_remunerasi_periode'] ?? 0, 0, ',', '.') }}
      </span></p>
                <p class="text-sm">Penilaian pending: <span class="font-medium">{{$kinerja['penilaian_pending']}}</span></p>
            @else
                <p class="text-sm text-gray-500">Belum ada periode aktif.</p>
            @endif
        </div>
    </div>
@endsection
