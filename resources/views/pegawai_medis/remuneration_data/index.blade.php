<x-app-layout title="Data Remunerasi">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">
        <h1 class="text-2xl font-semibold mb-6">Data Remunerasi</h1>

        <!-- Filter -->
        <section class="mb-8">
            <x-section>
                <h3 class="text-xl font-semibold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-filter"></i> Filter Data
                </h3>

                <form method="GET" action="{{ route('pegawai_medis.remuneration_data.index') }}" class="grid gap-6">
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <!-- Periode -->
                        <div>
                            <label class="block mb-2 font-medium text-slate-700">Periode:</label>
                            <select name="periode_id"
                                    class="w-full rounded-lg border px-3 py-2 focus:outline-none focus:border-blue-500">
                                @foreach($periodes as $p)
                                    <option value="{{ $p->id }}"
                                        {{ (int)($filters['periode_id'] ?? 0) === $p->id ? 'selected' : '' }}>
                                        {{ $p->name }} {{ $p->is_active ? '- (Aktif)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Unit -->
                        <div>
                            <label class="block mb-2 font-medium text-slate-700">Unit Kerja:</label>
                            <select name="unit_id"
                                    class="w-full rounded-lg border px-3 py-2 focus:outline-none focus:border-blue-500">
                                <option value="">Semua Unit</option>
                                @foreach($units as $u)
                                    <option value="{{ $u->id }}" {{ (string)$u->id === (string)($filters['unit_id'] ?? '') ? 'selected' : '' }}>
                                        {{ $u->nama_unit }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Profession -->
                        <div>
                            <label class="block mb-2 font-medium text-slate-700">Profesi:</label>
                            <select name="profesi_id"
                                    class="w-full rounded-lg border px-3 py-2 focus:outline-none focus:border-blue-500">
                                <option value="">Semua Profesi</option>
                                @foreach($profesis as $pf)
                                    <option value="{{ $pf->id }}" {{ (string)$pf->id === (string)($filters['profesi_id'] ?? '') ? 'selected' : '' }}>
                                        {{ $pf->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Search -->
                        <div>
                            <label class="block mb-2 font-medium text-slate-700">Cari:</label>
                            <input name="q" type="text" placeholder="Nama/NIP pegawai…"
                                   value="{{ $filters['q'] ?? '' }}"
                                   class="w-full rounded-lg border px-3 py-2 focus:outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('pegawai_medis.remuneration_data.index') }}"
                           class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-200 transition-colors">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </a>
                        <button type="submit"
                                class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm text-base">
                            <i class="fa-solid fa-filter"></i> Terapkan
                        </button>
                        <button type="button"
                                class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm text-base">
                            <i class="fa-solid fa-download"></i> Export
                        </button>

                        <!-- Per page -->
                        <div class="ml-auto">
                            <select name="per_page"
                                    class="rounded-xl border border-slate-300 px-3 py-2 bg-white">
                                @foreach([10,25,50,100] as $pp)
                                    <option value="{{ $pp }}" {{ (int)($filters['per_page'] ?? 25) === $pp ? 'selected' : '' }}>
                                        {{ $pp }} per halaman
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </form>
            </x-section>
        </section>

        <!-- Summary -->
        <section class="mb-8">
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 p-5 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg grid place-content-center text-white bg-gradient-to-tr from-cyan-500 to-sky-600">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <div class="text-xl font-semibold text-slate-800">{{ number_format($summary['totalPegawai']) }}</div>
                        <p class="text-slate-500 text-sm m-0">Total Pegawai</p>
                    </div>
                </div>

                <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 p-5 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg grid place-content-center text-white bg-gradient-to-tr from-cyan-500 to-sky-600">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                    <div>
                        <div class="text-xl font-semibold text-slate-800">
                            {{ $summary['avgRemun'] ? 'Rp '.number_format($summary['avgRemun'],0,',','.') : 'Rp 0' }}
                        </div>
                        <p class="text-slate-500 text-sm m-0">Rata-rata Remunerasi</p>
                    </div>
                </div>

                <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 p-5 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg grid place-content-center text-white bg-gradient-to-tr from-cyan-500 to-sky-600">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <div>
                        <div class="text-xl font-semibold text-slate-800">{{ number_format($summary['capaiPersen'],1) }}%</div>
                        <p class="text-slate-500 text-sm m-0">Memiliki Skor WSM</p>
                    </div>
                </div>

                <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 p-5 flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg grid place-content-center text-white bg-gradient-to-tr from-cyan-500 to-sky-600">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <div>
                        <div class="text-xl font-semibold text-slate-800">{{ $summary['periodeNama'] ?? '-' }}</div>
                        <p class="text-slate-500 text-sm m-0">Periode Aktif</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Tabel -->
        <x-section class="mb-12 overflow-hidden">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-4">
                <h3 class="text-xl font-semibold text-slate-800 m-0">
                    Data Remunerasi Pegawai - {{ $summary['periodeNama'] ?? '-' }}
                </h3>
                <div class="text-sm text-slate-500">
                    Menampilkan {{ $rows->firstItem() }}–{{ $rows->lastItem() }} dari {{ $rows->total() }} pegawai
                </div>
            </div>

            <x-ui.table min-width="1080px">
                <x-slot name="head">
                    <tr>
                        <th class="text-left px-3 py-3 whitespace-nowrap">No</th>
                        <th class="text-left px-3 py-3 whitespace-nowrap">NIP</th>
                        <th class="text-left px-3 py-3 whitespace-nowrap">Nama Pegawai</th>
                        <th class="text-left px-3 py-3 whitespace-nowrap">Unit Kerja</th>
                        <th class="text-left px-3 py-3 whitespace-nowrap">Profesi</th>
                        <th class="text-left px-3 py-3 whitespace-nowrap">Jabatan</th>
                        <th class="text-right px-3 py-3 whitespace-nowrap">Skor WSM</th>
                        <th class="text-right px-3 py-3 whitespace-nowrap">Remunerasi</th>
                        <th class="text-left px-3 py-3 whitespace-nowrap">Status</th>
                    </tr>
                </x-slot>
                @forelse($rows as $i => $r)
                    <tr class="hover:bg-slate-50">
                        <td class="px-3 py-3">{{ $rows->firstItem() + $i }}</td>
                        <td class="px-3 py-3">{{ $r->employee_number ?? '-' }}</td>
                        <td class="px-3 py-3 font-medium text-slate-800">{{ $r->name }}</td>
                        <td class="px-3 py-3">{{ $r->unit_nama ?? '-' }}</td>
                        <td class="px-3 py-3">{{ $r->profesi_nama ?? '-' }}</td>
                        <td class="px-3 py-3">{{ $r->position ?? '-' }}</td>
                        <td class="px-3 py-3 text-right">{{ $r->skor_wsm ? number_format($r->skor_wsm, 2) : '0.00' }}</td>
                        <td class="px-3 py-3 text-right"> {{ $r->nilai_remunerasi ? 'Rp '.number_format($r->nilai_remunerasi,0,',','.') : 'Rp 0' }} </td>
                        <td class="px-3 py-3">
                            @php $st = $r->status_pembayaran ?: 'Belum Dibayar'; @endphp
                            <span class="px-3 py-1 rounded-full text-xs font-semibold
                                    {{ $st==='Dibayar' ? 'bg-blue-100 text-blue-600' : '' }}
                                    {{ $st==='Belum Dibayar' ? 'bg-amber-100 text-amber-600' : '' }}
                                    {{ $st==='Ditahan' ? 'bg-red-100 text-red-600' : '' }}">
                                    {{ $st }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-3 py-6 text-center text-slate-500">Data tidak ditemukan.</td></tr>
                @endforelse
            </x-ui.table>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $rows->onEachSide(1)->withQueryString()->links() }}
            </div>
        </x-section>
    </div>
</x-app-layout>
