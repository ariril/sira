@extends('layouts.public')
@section('title','Data Remunerasi - Unit Remunerasi UNS')

@section('content')
    {{-- Page header/breadcrumb --}}
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

                <form x-data
                      @submit.prevent
                      class="grid gap-6">
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="block mb-2 font-medium text-slate-700">Periode:</label>
                            <select id="periode" class="w-full rounded-lg border-2 border-slate-200 px-3 py-2 focus:outline-none focus:border-blue-500">
                                <option value="">Pilih Periode</option>
                                <option>Juli 2025</option>
                                <option selected>Juni 2025</option>
                                <option>Mei 2025</option>
                                <option>April 2025</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 font-medium text-slate-700">Fakultas/Unit:</label>
                            <select id="fakultas" class="w-full rounded-lg border-2 border-slate-200 px-3 py-2 focus:outline-none focus:border-blue-500">
                                <option>Semua Unit</option><option>FKIP</option><option>Fakultas Ekonomi & Bisnis</option>
                                <option>FMIPA</option><option>Fakultas Hukum</option><option>Fakultas Teknik</option><option>Rektorat</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 font-medium text-slate-700">Golongan:</label>
                            <select id="golongan" class="w-full rounded-lg border-2 border-slate-200 px-3 py-2 focus:outline-none focus:border-blue-500">
                                <option>Semua Golongan</option><option>Golongan IV</option><option>Golongan III</option><option>Golongan II</option><option>Golongan I</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 font-medium text-slate-700">Cari:</label>
                            <input id="search" type="text" placeholder="Nama/NIP pegawai..."
                                   class="w-full rounded-lg border-2 border-slate-200 px-3 py-2 focus:outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
                        <button type="reset" class="btn-outline"><i class="fa-solid fa-rotate-right"></i> Reset</button>
                        <button type="button" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-white bg-gradient-to-tr from-emerald-500 to-emerald-600 hover:-translate-y-0.5 transition">
                            <i class="fa-solid fa-download"></i> Export
                        </button>
                    </div>
                </form>
            </div>
        </section>

        {{-- Summary --}}
        <section class="mb-8">
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @php
                    $cards = [
                      ['icon'=>'fa-users','value'=>'245','label'=>'Total Pegawai'],
                      ['icon'=>'fa-money-bill-wave','value'=>'Rp 2.4M','label'=>'Rata-rata Remunerasi'],
                      ['icon'=>'fa-chart-line','value'=>'92.5%','label'=>'Capaian Target'],
                      ['icon'=>'fa-calendar-check','value'=>'Juni 2025','label'=>'Periode Aktif'],
                    ];
                @endphp
                @foreach($cards as $c)
                    <div class="bg-white rounded-xl shadow p-5 flex items-center gap-3">
                        <div class="w-12 h-12 rounded-lg grid place-content-center text-white bg-gradient-to-tr from-violet-500 to-violet-600">
                            <i class="fa-solid {{ $c['icon'] }}"></i>
                        </div>
                        <div>
                            <div class="text-xl font-semibold text-slate-800">{{ $c['value'] }}</div>
                            <p class="text-slate-500 text-sm m-0">{{ $c['label'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Tabel --}}
        <section x-data="tableUX()" class="bg-white rounded-xl shadow p-6 mb-12 overflow-hidden">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-4">
                <h3 class="text-xl font-semibold text-slate-800 m-0">Data Remunerasi Pegawai - Juni 2025</h3>
                <select x-model="perPage" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="10">10 per halaman</option>
                    <option value="25">25 per halaman</option>
                    <option value="50">50 per halaman</option>
                    <option value="100">100 per halaman</option>
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 sticky top-0">
                    <tr>
                        <template x-for="(h,i) in headers" :key="i">
                            <th @click="sortBy(i)"
                                class="text-left font-semibold text-slate-700 px-3 py-3 border-b cursor-pointer hover:bg-slate-100">
                                <span x-text="h"></span> <i class="fa-solid fa-sort ml-1 text-slate-400"></i>
                            </th>
                        </template>
                        <th class="text-left font-semibold text-slate-700 px-3 py-3 border-b">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    {{-- contoh 4 baris sesuai mockup (bisa lanjut dari DB nanti) --}}
                    @php
                        $rows = [
                          ['1','196805251993031001','Dr. Ahmad Sutrisno, M.Si.','FKIP','IV/c','Lektor Kepala','100','95','95.0%','Rp 3.250.000','Approved'],
                          ['2','197203151998022001','Dra. Sri Wahyuni, M.Pd.','FMIPA','IV/a','Lektor','100','88','88.0%','Rp 2.850.000','Approved'],
                          ['3','198009121995123002','Budi Santoso, S.E., M.M.','FEB','III/d','Asisten Ahli','100','92','92.0%','Rp 2.450.000','Pending'],
                          ['4','197511031999031003','Ir. Hendra Pratama, M.T.','FT','IV/b','Lektor','100','96','96.0%','Rp 3.100.000','Approved'],
                        ];
                    @endphp
                    @foreach($rows as $r)
                        <tr class="border-b hover:bg-slate-50">
                            @for($i=0;$i<10;$i++)
                                <td class="px-3 py-3">{{ $r[$i] }}</td>
                            @endfor
                            <td class="px-3 py-3">
                                @php $status=$r[10]; @endphp
                                <span class="px-3 py-1 rounded-full text-xs font-semibold
                  {{ $status==='Approved'?'bg-blue-100 text-blue-600':'' }}
                  {{ $status==='Pending'?'bg-amber-100 text-amber-600':'' }}
                ">{{ $status }}</span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination dummy --}}
            <div class="flex justify-center gap-2 mt-6">
                <button class="px-3 py-1.5 border rounded hover:bg-slate-50"><i class="fa-solid fa-angle-left"></i></button>
                <button class="px-3 py-1.5 border rounded bg-blue-600 text-white">1</button>
                <button class="px-3 py-1.5 border rounded hover:bg-slate-50">2</button>
                <button class="px-3 py-1.5 border rounded hover:bg-slate-50">3</button>
                <button class="px-3 py-1.5 border rounded hover:bg-slate-50"><i class="fa-solid fa-angle-right"></i></button>
            </div>
        </section>

    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('tableUX', () => ({
                headers: ['No','NIP','Nama Pegawai','Unit Kerja','Golongan','Jabatan','Target SKP','Realisasi SKP','Capaian (%)','Remunerasi'],
                perPage: 25,
                sortIndex: null,
                asc: true,
                sortBy(i){ this.asc = this.sortIndex===i ? !this.asc : true; this.sortIndex=i; /* implement server/JS sort jika nanti perlu */ },
            }))
        })
    </script>
@endpush
