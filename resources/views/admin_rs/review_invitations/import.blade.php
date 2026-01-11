<x-app-layout title="Import Review Invitations">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Import Invitation Link (Excel)</h1>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        @if(!empty($periodWarning))
            <div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
                {{ $periodWarning }}
            </div>
        @endif

        @if(!empty($errorMessage))
            <div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
                {{ $errorMessage }}
            </div>
        @endif

        @if($summary)
            @php
                $s = (int) ($summary['success'] ?? 0);
                $f = (int) ($summary['failed'] ?? 0);
                $sk = (int) ($summary['skipped'] ?? 0);
                $total = $s + $f + $sk;

                $bannerClasses = 'border-slate-200 bg-slate-50 text-slate-700';
                if ($total > 0) {
                    if ($f > 0) {
                        $bannerClasses = 'border-rose-200 bg-rose-50 text-rose-800';
                    } elseif ($sk > 0) {
                        $bannerClasses = 'border-amber-200 bg-amber-50 text-amber-800';
                    } elseif ($s > 0) {
                        $bannerClasses = 'border-emerald-200 bg-emerald-50 text-emerald-800';
                    }
                }
            @endphp
            <div class="rounded-xl border {{ $bannerClasses }} px-4 py-3 text-sm flex flex-wrap items-center gap-2">
                <span class="font-semibold">Ringkasan Import:</span>
                <span>Total: {{ $total }}</span>
                <span>• Sukses: {{ $s }}</span>
                <span>• Gagal: {{ $f }}</span>
                <span>• Skip: {{ $sk }}</span>
                @if($total === 0)
                    <span class="text-slate-600">Tidak ada baris diproses.</span>
                @elseif($s === 0 && $f === 0 && $sk > 0)
                    <span class="text-slate-600">Semua baris di-skip (cek duplikat atau data tidak lengkap).</span>
                @elseif($f > 0)
                    <span class="text-slate-600">Periksa detail di tabel hasil untuk baris gagal.</span>
                @endif
            </div>
        @endif

        @if($period ?? null)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 px-4 py-3 text-sm">
                    Import menggunakan periode: <span class="font-semibold">{{ $period->name ?? '-' }}</span>.
                </div>

                @if(!empty($periodOptions) && count($periodOptions) > 1)
                    <form method="GET" class="mb-4">
                        <div class="grid md:grid-cols-2 gap-4 items-end">
                            <div>
                                <label class="block text-sm font-medium text-slate-600 mb-1">Pilih Periode</label>
                                <x-ui.select name="period_id" :options="$periodOptions" :value="$selectedPeriodId ?? null" />
                            </div>
                            <div class="flex justify-end">
                                <button type="submit"
                                    class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                                    <i class="fa-solid fa-filter"></i>
                                    Terapkan
                                </button>
                            </div>
                        </div>
                    </form>
                @endif

                <form method="POST" action="{{ route('admin_rs.review_invitations.import.process') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    @if(!empty($selectedPeriodId))
                        <input type="hidden" name="period_id" value="{{ (int) $selectedPeriodId }}" />
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-2">File Excel/CSV</label>
                        <label id="ri-dropzone" class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-slate-100 border-slate-200 transition duration-200">
                            <div id="ri-drop-label" class="flex flex-col items-center justify-center pt-5 pb-6 text-slate-600 transition duration-200">
                                <i id="ri-drop-icon" class="fa-solid fa-file-excel text-3xl mb-2 text-emerald-500 transition duration-200"></i>
                                <p class="text-sm" id="ri-drop-default"><span class="font-semibold">Klik untuk pilih</span> atau tarik & lepas</p>
                                <p class="text-sm hidden text-center" id="ri-drop-selected">
                                    <span class="font-semibold">File dipilih:</span>
                                    <span id="ri-drop-selected-name"></span>
                                    <span class="block text-xs text-slate-500 mt-1">Klik untuk pilih ulang atau tarik & lepas file lainnya</span>
                                </p>
                                <p class="text-xs text-slate-500">.xlsx, .xls, .csv • Maks. 5 MB</p>
                            </div>
                            <input id="ri-file" type="file" name="file" accept=".xlsx,.xls,.csv,text/csv" class="hidden" required />
                        </label>
                        <p class="mt-2 text-xs text-slate-500" id="ri-file-name"></p>
                        @error('file')
                            <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <script>
                        document.addEventListener('DOMContentLoaded', function(){
                            const input = document.getElementById('ri-file');
                            const nameEl = document.getElementById('ri-file-name');
                            const defaultPrompt = document.getElementById('ri-drop-default');
                            const selectedPrompt = document.getElementById('ri-drop-selected');
                            const selectedName = document.getElementById('ri-drop-selected-name');
                            const dropzone = document.getElementById('ri-dropzone');
                            const dropLabel = document.getElementById('ri-drop-label');
                            const dropIcon = document.getElementById('ri-drop-icon');
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

                    <div class="flex justify-end">
                        <x-ui.button type="submit" variant="success" class="h-12 px-6 text-base">
                            <i class="fa-solid fa-file-arrow-up mr-2"></i> Unggah & Import
                        </x-ui.button>
                    </div>
                </form>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
            <h2 class="text-base font-semibold text-slate-800">Format Kolom</h2>
            <p class="text-sm text-slate-600">Gunakan header berikut (case-insensitive). Setiap kolom di Excel adalah kolom terpisah.</p>

            @php
                $sample = "registration_ref,patient_name,phone,email,unit,staff_numbers\n" .
                          "REG-2024-009812,Maria L.,08xxx,maria@example.com,Poli Umum,D001;P012\n" .
                          "REG-2024-009999,Budi S.,0812xxx,budi@example.com,UGD,D005;P020";
            @endphp

            <pre class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-[13px] overflow-auto">{{ $sample }}</pre>

            <ul class="list-disc list-inside text-sm text-slate-600 space-y-1">
                <li><span class="font-semibold">staff_numbers</span> pisahkan dengan tanda titik koma (<code>;</code>)</li>
                <li><span class="font-semibold">email</span> wajib dan harus format valid</li>
                <li><span class="font-semibold">phone</span> opsional; jika kosong biarkan sel kosong</li>
            </ul>
        </div>

        @if($summary)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-wrap items-center justify-between gap-4">
                <div class="text-sm text-slate-700">
                    <span class="font-semibold">Sukses:</span> {{ $summary['success'] ?? 0 }}
                    <span class="mx-2">•</span>
                    <span class="font-semibold">Gagal:</span> {{ $summary['failed'] ?? 0 }}
                    <span class="mx-2">•</span>
                    <span class="font-semibold">Skip:</span> {{ $summary['skipped'] ?? 0 }}
                </div>

                <div>
                    <a href="{{ route('admin_rs.review_invitations.import.export') }}" class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-2xl text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200">
                        Export CSV (Sukses)
                    </a>
                </div>
            </div>
        @endif

        @if(!empty($results))
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
                <div class="font-medium">Hasil Import</div>

                <x-ui.table min-width="1200px">
                    <x-slot name="head">
                        <tr>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Row</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Registration Ref</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Pasien</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Kontak</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Unit</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Alasan</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Link Undangan</th>
                        </tr>
                    </x-slot>

                    @foreach($results as $r)
                        @php
                            $st = strtolower((string)($r['status'] ?? ''));
                            $badgeMap = [
                                'success' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                'failed' => 'bg-rose-50 text-rose-700 border-rose-100',
                                'skipped' => 'bg-amber-50 text-amber-800 border-amber-100',
                            ];
                            $badgeClass = $badgeMap[$st] ?? 'bg-slate-50 text-slate-700 border-slate-200';
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">{{ $r['row'] ?? '' }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border {{ $badgeClass }}">
                                    {{ strtoupper((string)($r['status'] ?? '')) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 font-medium text-slate-800">{{ $r['registration_ref'] ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $r['patient_name'] ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $r['contact'] ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $r['unit'] ?? '-' }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ $r['message'] ?? '' }}</td>
                            <td class="px-6 py-4">
                                @if(!empty($r['link_undangan']))
                                    <a class="text-indigo-600 hover:underline break-all" href="{{ $r['link_undangan'] }}" target="_blank" rel="noreferrer">{{ $r['link_undangan'] }}</a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>
        @endif
    </div>
</x-app-layout>
