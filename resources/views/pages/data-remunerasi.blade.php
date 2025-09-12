@extends('layouts.public')
@section('title','Data Remunerasi - Unit Remunerasi')

@section('content')
    {{-- Header --}}
    <section class="bg-gradient-to-tr from-indigo-900 to-blue-500 text-white py-10 mb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <h1 class="text-4xl font-semibold mb-2">Data Remunerasi</h1>
            <nav class="opacity-90 flex items-center gap-2 text-sm">
                <a href="{{ route('home') }}" class="hover:underline">Beranda</a>
                <i class="fa-solid fa-chevron-right text-xs"></i>
                <span>Data Remunerasi</span>
            </nav>
        </div>
    </section>

    <div class="max-w-7xl mx-auto px-4 sm:px-6">

        {{-- Filter --}}
        <section class="mb-8">
            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-xl font-semibold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-filter"></i> Filter Data
                </h3>

                <form method="GET" action="{{ route('data') }}" class="grid gap-6">
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        {{-- Periode --}}
                        <div>
                            <label class="block mb-2 font-medium text-slate-700">Periode:</label>
                            <select name="periode_id"
                                    class="w-full rounded-lg border-2 border-slate-200 px-3 py-2 focus:outline-none focus:border-blue-500">
                                @foreach($periodes as $p)
                                    <option value="{{ $p->id }}"
                                        {{ (int)($filters['periode_id'] ?? 0) === $p->id ? 'selected' : '' }}>
                                        {{ $p->nama_periode }} {{ $p->is_active ? '- (Aktif)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Unit --}}
                        <div>
                            <label class="block mb-2 font-medium text-slate-700">Unit Kerja:</label>
                            <select name="unit_id"
                                    class="w-full rounded-lg border-2 border-slate-200 px-3 py-2 focus:outline-none focus:border-blue-500">
                                <option value="">Semua Unit</option>
                                @foreach($units as $u)
                                    <option value="{{ $u->id }}" {{ (string)$u->id === (string)($filters['unit_id'] ?? '') ? 'selected' : '' }}>
                                        {{ $u->nama_unit }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Profesi --}}
                        <div>
                            <label class="block mb-2 font-medium text-slate-700">Profesi:</label>
                            <select name="profesi_id"
                                    class="w-full rounded-lg border-2 border-slate-200 px-3 py-2 focus:outline-none focus:border-blue-500">
                                <option value="">Semua Profesi</option>
                                @foreach($profesis as $pf)
                                    <option value="{{ $pf->id }}" {{ (string)$pf->id === (string)($filters['profesi_id'] ?? '') ? 'selected' : '' }}>
                                        {{ $pf->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Search --}}
                        <div>
                            <label class="block mb-2 font-medium text-slate-700">Cari:</label>
                            <input name="q" type="text" placeholder="Nama/NIP pegawai…"
                                   value="{{ $filters['q'] ?? '' }}"
                                   class="w-full rounded-lg border-2 border-slate-200 px-3 py-2 focus:outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-magnifying-glass"></i> Terapkan
                        </button>
                        <a href="{{ route('data') }}" class="btn-outline">
                            <i class="fa-solid fa-rotate-right"></i> Reset
                        </a>
                        <button type="button"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-white bg-gradient-to-tr from-emerald-500 to-emerald-600 hover:-translate-y-0.5 transition">
                            <i class="fa-solid fa-download"></i> Export
                        </button>

                        {{-- Per page --}}
                        <div class="ml-auto">
                            <select name="per_page"
                                    class="rounded-lg border-2 border-slate-200 px-3 py-2">
                                @foreach([10,25,50,100] as $pp)
                                    <option value="{{ $pp }}" {{ (int)($filters['per_page'] ?? 25) === $pp ? 'selected' : '' }}>
                                        {{ $pp }} per halaman
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        {{-- Summary --}}
        <section class="mb-8">
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-white rounded-xl shadow p-5 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg grid place-content-center text-white bg-gradient-to-tr from-violet-500 to-violet-600">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <div class="text-xl font-semibold text-slate-800">{{ number_format($summary['totalPegawai']) }}</div>
                        <p class="text-slate-500 text-sm m-0">Total Pegawai</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow p-5 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg grid place-content-center text-white bg-gradient-to-tr from-emerald-500 to-emerald-600">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                    <div>
                        <div class="text-xl font-semibold text-slate-800">
                            {{ $summary['avgRemun'] ? 'Rp '.number_format($summary['avgRemun'],0,',','.') : 'Rp 0' }}
                        </div>
                        <p class="text-slate-500 text-sm m-0">Rata-rata Remunerasi</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow p-5 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg grid place-content-center text-white bg-gradient-to-tr from-sky-500 to-blue-600">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <div>
                        <div class="text-xl font-semibold text-slate-800">{{ number_format($summary['capaiPersen'],1) }}%</div>
                        <p class="text-slate-500 text-sm m-0">Memiliki Skor WSM</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow p-5 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg grid place-content-center text-white bg-gradient-to-tr from-amber-500 to-amber-600">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <div>
                        <div class="text-xl font-semibold text-slate-800">{{ $summary['periodeNama'] ?? '-' }}</div>
                        <p class="text-slate-500 text-sm m-0">Periode Aktif</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Tabel --}}
        <section class="bg-white rounded-xl shadow p-6 mb-12 overflow-hidden">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-4">
                <h3 class="text-xl font-semibold text-slate-800 m-0">
                    Data Remunerasi Pegawai - {{ $summary['periodeNama'] ?? '-' }}
                </h3>
                <div class="text-sm text-slate-500">
                    Menampilkan {{ $rows->firstItem() }}–{{ $rows->lastItem() }} dari {{ $rows->total() }} pegawai
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 sticky top-0">
                    <tr>
                        <th class="text-left font-semibold text-slate-700 px-3 py-3 border-b">No</th>
                        <th class="text-left font-semibold text-slate-700 px-3 py-3 border-b">NIP</th>
                        <th class="text-left font-semibold text-slate-700 px-3 py-3 border-b">Nama Pegawai</th>
                        <th class="text-left font-semibold text-slate-700 px-3 py-3 border-b">Unit Kerja</th>
                        <th class="text-left font-semibold text-slate-700 px-3 py-3 border-b">Profesi</th>
                        <th class="text-left font-semibold text-slate-700 px-3 py-3 border-b">Jabatan</th>
                        <th class="text-right font-semibold text-slate-700 px-3 py-3 border-b">Skor WSM</th>
                        <th class="text-right font-semibold text-slate-700 px-3 py-3 border-b">Remunerasi</th>
                        <th class="text-left font-semibold text-slate-700 px-3 py-3 border-b">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $i => $r)
                        <tr class="border-b hover:bg-slate-50">
                            <td class="px-3 py-3">{{ $rows->firstItem() + $i }}</td>
                            <td class="px-3 py-3">{{ $r->nip ?? '-' }}</td>
                            <td class="px-3 py-3 font-medium text-slate-800">{{ $r->nama }}</td>
                            <td class="px-3 py-3">{{ $r->unit_nama ?? '-' }}</td>
                            <td class="px-3 py-3">{{ $r->profesi_nama ?? '-' }}</td>
                            <td class="px-3 py-3">{{ $r->jabatan ?? '-' }}</td>
                            <td class="px-3 py-3 text-right">{{ $r->skor_wsm ? number_format($r->skor_wsm, 2) : '0.00' }}</td>
                            <td class="px-3 py-3 text-right"> {{ $r->nilai_remunerasi ? 'Rp '.number_format($r->nilai_remunerasi,0,',','.') : 'Rp 0' }} </td>
                            <td class="px-3 py-3">
                                @php $st = $r->status_pembayaran ?: 'Belum Dibayar'; @endphp
                                <span class="px-3 py-1 rounded-full text-xs font-semibold
                                        {{ $st==='Berhasil' || $st==='Dibayar' ? 'bg-blue-100 text-blue-600' : '' }}
                                        {{ $st==='Menunggu' || $st==='Belum Dibayar' ? 'bg-amber-100 text-amber-600' : '' }}
                                        {{ $st==='Gagal' ? 'bg-red-100 text-red-600' : '' }}">
                                        {{ $st }}
                                    </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-6 text-center text-slate-500">Data tidak ditemukan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="mt-6">
                {{ $rows->onEachSide(1)->links() }}
            </div>
        </section>

    </div>
@endsection
