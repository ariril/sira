<x-app-layout title="Detail Batch Import">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Detail Batch Import</h1>
            <x-ui.button as="a" href="{{ route('admin_rs.attendances.batches') }}" variant="success" class="h-12 px-6 text-base">
                <i class="fa-solid fa-database mr-2"></i> Semua Batch
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">

        @if($batch->is_superseded)
            <div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3">
                Batch ini telah digantikan oleh upload berikutnya pada periode yang sama. Datanya disimpan sebagai riwayat dan tidak dipakai untuk perhitungan.
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="grid gap-4 sm:grid-cols-2 text-sm">
                <div>
                    <div class="text-slate-500">Waktu Import</div>
                    <div class="font-medium text-slate-800">{{ $batch->imported_at?->format('d M Y H:i') }}</div>
                </div>
                <div>
                    <div class="text-slate-500">Diunggah Oleh</div>
                    <div class="font-medium text-slate-800">{{ $batch->importer->name ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-slate-500">File</div>
                    <div class="font-medium text-slate-800">
                        {{ $batch->file_name }}
                        @if($batch->is_superseded)
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Digantikan</span>
                        @endif
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <div class="text-slate-500">Total</div>
                        <div class="font-medium text-slate-800">{{ $batch->total_rows }}</div>
                    </div>
                    <div>
                        <div class="text-slate-500">Berhasil</div>
                        <div class="font-medium text-emerald-700">{{ $batch->success_rows }}</div>
                    </div>
                    <div>
                        <div class="text-slate-500">Gagal</div>
                        <div class="font-medium text-rose-700">{{ $batch->failed_rows }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <form method="GET">
                <div class="grid gap-5 md:grid-cols-12">
                    <div class="md:col-span-4">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                        <x-ui.input name="q" placeholder="Nama pegawai / NIP" addonLeft="fa-magnifying-glass"
                            :value="$q" class="focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>

                    <div class="md:col-span-4">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Hasil import</label>
                        @php
                            $resultOptions = [
                                'all' => 'Semua',
                                'success' => 'Berhasil',
                                'failed' => 'Gagal',
                            ];
                        @endphp
                        <x-ui.select id="result" name="result" :options="$resultOptions" :value="$result"
                            class="focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Jumlah per halaman</label>
                        @php
                            $perPageSelectOptions = [];
                            foreach ($perPageOptions as $option) {
                                $perPageSelectOptions[$option] = $option.' / halaman';
                            }
                        @endphp
                        <x-ui.select id="per_page" name="per_page" :options="$perPageSelectOptions" :value="$perPage"
                            class="focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('admin_rs.attendances.batches.show', $batch) }}"
                        class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                        <i class="fa-solid fa-rotate-left"></i>
                        Reset
                    </a>
                    <button type="submit"
                        class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                        <i class="fa-solid fa-filter"></i> Terapkan
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-800">Data Absensi Hasil Import</h2>
                <div class="text-sm text-slate-600">Total baris: <span class="font-medium text-slate-800">{{ $rows->total() }}</span></div>
            </div>

            <x-ui.table min-width="900px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Row</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Pegawai</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">NIP</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Tanggal</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Masuk</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Pulang</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                    </tr>
                </x-slot>
                @forelse($rows as $r)
                    @php
                        $parsed = $r->parsed_data ?? [];
                        $raw = $r->raw_data ?? [];

                        $rawDate = $parsed['attendance_date'] ?? ($raw['attendance_date'] ?? ($raw['tanggal'] ?? null));
                        $dateKey = null;
                        $dateLabel = '-';
                        if ($rawDate) {
                            try {
                                $dateKey = \Carbon\Carbon::parse($rawDate)->format('Y-m-d');
                                $dateLabel = \Carbon\Carbon::parse($rawDate)->format('d M Y');
                            } catch (\Throwable $e) {
                                $dateLabel = $rawDate;
                            }
                        }

                        $attendanceKey = ($r->user_id && $dateKey) ? ($r->user_id.'|'.$dateKey) : null;
                        $attendance = $attendanceKey && isset($attendanceMap[$attendanceKey]) ? $attendanceMap[$attendanceKey] : null;

                        $checkIn = $attendance?->check_in ? \Carbon\Carbon::parse($attendance->check_in)->format('H:i') : ($parsed['check_in'] ?? null);
                        $checkOut = $attendance?->check_out ? \Carbon\Carbon::parse($attendance->check_out)->format('H:i') : ($parsed['check_out'] ?? null);

                        $statusText = $attendance?->attendance_status?->value ?? ($raw['status'] ?? null);
                        if (!$statusText) {
                            $statusText = $r->success ? 'Berhasil' : 'Gagal';
                        }

                        $employeeNumber = $r->user->employee_number ?? ($r->employee_number ?? '-');
                    @endphp
                    <tr class="hover:bg-slate-50 {{ $r->success ? '' : 'bg-rose-50/40' }}">
                        <td class="px-6 py-4">{{ $r->row_no ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $r->user->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $employeeNumber }}</td>
                        <td class="px-6 py-4">{{ $dateLabel }}</td>
                        <td class="px-6 py-4">{{ $checkIn ? $checkIn : '-' }}</td>
                        <td class="px-6 py-4">{{ $checkOut ? $checkOut : '-' }}</td>
                        <td class="px-6 py-4 {{ $r->success ? '' : 'text-rose-700 font-medium' }}">
                            <span>{{ $statusText ?? '-' }}</span>
                            @if(!$r->success && $r->error_message)
                                <span class="ml-2 inline-flex items-center text-slate-500" title="{{ $r->error_message }}">
                                    <i class="fa-solid fa-circle-info"></i>
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
                @endforelse
            </x-ui.table>
            <div class="pt-2 flex justify-end">{{ $rows->withQueryString()->links() }}</div>
        </div>
    </div>
</x-app-layout>
