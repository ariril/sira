<x-app-layout title="Manual Metrics">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Manual Metrics</h1>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        @unless($activePeriod ?? null)
            <div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
                <div class="font-semibold">Tidak ada periode yang aktif saat ini.</div>
                <div>Aktifkan periode penilaian agar unggahan metrics diproses.</div>
            </div>
        @endunless

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
            <div class="font-medium">Unduh Template Excel per Kriteria</div>
            <form method="POST" action="{{ route('admin_rs.metrics.template') }}" class="space-y-4">
                @csrf
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kriteria</label>
                        <x-ui.select name="performance_criteria_id" :options="$criteriaOptions" placeholder="Pilih kriteria" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Periode (opsional)</label>
                        <x-ui.select name="period_id" :options="$periods" placeholder="Pakai Periode Aktif" />
                    </div>
                </div>
                <p class="text-xs text-slate-500">Template memuat daftar pegawai dan kolom nilai yang mengikuti tipe data kriteria.</p>
                <x-ui.button type="submit" variant="success" class="h-11 px-5">Generate Excel</x-ui.button>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
            <div class="font-medium">Upload Excel/CSV</div>
            <form method="POST" action="{{ route('admin_rs.metrics.upload_csv') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kriteria</label>
                        <x-ui.select name="performance_criteria_id" :options="$criteriaOptions" placeholder="Pilih kriteria" required />
                    </div>
                    <div class="flex items-end">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" id="replace_existing" name="replace_existing" value="1" class="mt-1">
                            <label for="replace_existing" class="text-sm text-slate-700">Timpa nilai pada periode aktif bila sudah ada. Sistem akan memakai Periode Aktif secara otomatis.</label>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">File Excel/CSV</label>
                    <label id="metrics-dropzone" class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-slate-100 border-slate-200 transition duration-200">
                        <div id="metrics-drop-label" class="flex flex-col items-center justify-center pt-5 pb-6 text-slate-600 transition duration-200">
                            <i id="metrics-drop-icon" class="fa-solid fa-file-excel text-3xl mb-2 text-emerald-500 transition duration-200"></i>
                            <p class="text-sm" id="metrics-drop-default"><span class="font-semibold">Klik untuk pilih</span> atau tarik & lepas</p>
                            <p class="text-sm hidden text-center" id="metrics-drop-selected">
                                <span class="font-semibold">File dipilih:</span>
                                <span id="metrics-drop-selected-name"></span>
                                <span class="block text-xs text-slate-500 mt-1">Klik untuk pilih ulang atau tarik & lepas file lainnya</span>
                            </p>
                            <p class="text-xs text-slate-500">.xlsx, .xls, .csv • Maks. 5 MB</p>
                        </div>
                        <input id="metrics-file" type="file" name="file" accept=".xlsx,.xls,.csv,text/csv" class="hidden" required />
                    </label>
                    <p class="mt-2 text-xs text-slate-500" id="metrics-file-name"></p>
                    <script>
                        document.addEventListener('DOMContentLoaded', function(){
                            const input = document.getElementById('metrics-file');
                            const nameEl = document.getElementById('metrics-file-name');
                            const defaultPrompt = document.getElementById('metrics-drop-default');
                            const selectedPrompt = document.getElementById('metrics-drop-selected');
                            const selectedName = document.getElementById('metrics-drop-selected-name');
                            const dropzone = document.getElementById('metrics-dropzone');
                            const dropLabel = document.getElementById('metrics-drop-label');
                            const dropIcon = document.getElementById('metrics-drop-icon');
                            if (!input) return;

                            const resetState = () => {
                                if (defaultPrompt) defaultPrompt.classList.remove('hidden');
                                if (selectedPrompt) selectedPrompt.classList.add('hidden');
                                if (selectedName) selectedName.textContent = '';
                                if (nameEl) nameEl.textContent = '';
                                if (dropzone) {
                                    dropzone.classList.remove('bg-emerald-50','border-emerald-300','shadow-inner');
                                    dropzone.classList.add('bg-slate-50','border-slate-200');
                                }
                                if (dropLabel) {
                                    dropLabel.classList.remove('text-emerald-700');
                                    dropLabel.classList.add('text-slate-600');
                                }
                                if (dropIcon) {
                                    dropIcon.classList.remove('text-emerald-600');
                                    dropIcon.classList.add('text-emerald-500');
                                }
                            };

                            input.addEventListener('change', () => {
                                const file = input.files && input.files.length ? input.files[0] : null;
                                if (file) {
                                    if (defaultPrompt) defaultPrompt.classList.add('hidden');
                                    if (selectedPrompt) selectedPrompt.classList.remove('hidden');
                                    if (selectedName) selectedName.textContent = file.name;
                                    if (nameEl) nameEl.textContent = 'Dipilih: ' + file.name;
                                    if (dropzone) {
                                        dropzone.classList.remove('bg-slate-50','border-slate-200');
                                        dropzone.classList.add('bg-emerald-50','border-emerald-300','shadow-inner');
                                    }
                                    if (dropLabel) {
                                        dropLabel.classList.remove('text-slate-600');
                                        dropLabel.classList.add('text-emerald-700');
                                    }
                                    if (dropIcon) {
                                        dropIcon.classList.remove('text-emerald-500');
                                        dropIcon.classList.add('text-emerald-600');
                                    }
                                } else {
                                    resetState();
                                }
                            });

                            resetState();
                        });
                    </script>
                </div>
                <div class="flex justify-end">
                    <x-ui.button type="submit" variant="success" class="h-12 px-6 text-base">
                        <i class="fa-solid fa-file-arrow-up mr-2"></i> Unggah & Import
                    </x-ui.button>
                </div>
            </form>
            <div class="text-xs text-slate-500 mt-1">
                Gunakan template agar header sesuai tipe data kriteria. Periode otomatis memakai Periode Aktif.
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <form method="GET">
                @php
                    $perPageSelectOptions = [];
                    if (isset($perPageOptions) && is_iterable($perPageOptions)) {
                        foreach ($perPageOptions as $n) {
                            $perPageSelectOptions[$n] = $n . ' / halaman';
                        }
                    }
                @endphp
                <div class="grid gap-5 md:grid-cols-12">
                    <div class="md:col-span-4">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                        <x-ui.input name="q" placeholder="Nama pegawai / periode / kriteria" addonLeft="fa-magnifying-glass"
                            :value="$q" class="focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                        <x-ui.select name="period_id" :options="$periods" :value="$periodId" placeholder="(Semua)"
                            class="focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Kriteria</label>
                        <x-ui.select name="criteria_id" :options="$criteriaOptions" :value="$criteriaId" placeholder="(Semua)"
                            class="focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Tampil</label>
                        <x-ui.select name="per_page" :options="$perPageSelectOptions" :value="$perPage"
                            class="focus:border-emerald-500 focus:ring-emerald-500" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('admin_rs.metrics.index') }}"
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

        <x-ui.table min-width="980px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left">Pegawai</th>
                    <th class="px-6 py-4 text-left">NIP</th>
                    <th class="px-6 py-4 text-left">Kriteria</th>
                    <th class="px-6 py-4 text-left">Periode</th>
                    <th class="px-6 py-4 text-left">Nilai</th>
                    <th class="px-6 py-4 text-left">Sumber</th>
                </tr>
            </x-slot>
            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">{{ $it->user->name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->user->employee_number ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->criteria->name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->period->name ?? '-' }}</td>
                    <td class="px-6 py-4">
                        @php
                            $valueDisplay = '-';
                            if (!is_null($it->value_numeric)) {
                                $valueDisplay = (int) $it->value_numeric;
                            } elseif (!empty($it->value_datetime)) {
                                $valueDisplay = \Illuminate\Support\Carbon::parse($it->value_datetime)->format('d M Y H:i');
                            } elseif (!empty($it->value_text)) {
                                $valueDisplay = $it->value_text;
                            }
                        @endphp
                        {{ $valueDisplay }}
                    </td>
                    <td class="px-6 py-4">{{ $it->source_type }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
            @endforelse
        </x-ui.table>

        <div class="pt-2 flex justify-end">{{ $items->withQueryString()->links() }}</div>
    </div>
</x-app-layout>
