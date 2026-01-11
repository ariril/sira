<x-app-layout title="Ubah Tugas Tambahan">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Ubah Tugas Tambahan</h1>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <form
                method="POST"
                action="{{ route('kepala_unit.additional-tasks.update', $item->id) }}"
                enctype="multipart/form-data"
                class="grid md:grid-cols-2 gap-5"
                x-data="{
                    bonus: @js(old('bonus_amount', $item->bonus_amount)),
                    points: @js(old('points', $item->points)),
                    cancelWindow: @js(old('cancel_window_hours', $item->cancel_window_hours ?? 24)),
                    penaltyType: @js(old('default_penalty_type', $item->default_penalty_type ?? 'none')),
                    penaltyValue: @js(old('default_penalty_value', $item->default_penalty_value ?? 0)),
                    penaltyBase: @js(old('penalty_base', $item->penalty_base ?? 'task_bonus')),
                }"
            >
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="assessment_period_id" :options="$periods->pluck('name','id')" :value="$item->assessment_period_id" placeholder="Pilih periode" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Judul</label>
                    <x-ui.input name="title" :value="$item->title" required />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Deskripsi</label>
                    <x-ui.textarea name="description" rows="4" :value="$item->description" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Mulai</label>
                    <x-ui.input type="date" name="start_date" :value="\Carbon\Carbon::parse($item->start_date)->format('Y-m-d')" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Waktu Mulai (WIB)</label>
                    <x-ui.input type="time" name="start_time" :value="$item->start_time ? \Carbon\Carbon::parse($item->start_time)->format('H:i') : '08:00'" step="60" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tanggal Selesai</label>
                    <x-ui.input type="date" name="due_date" :value="\Carbon\Carbon::parse($item->due_date)->format('Y-m-d')" required />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Waktu Selesai (WIB)</label>
                    <x-ui.input type="time" name="due_time" :value="$item->due_time ? \Carbon\Carbon::parse($item->due_time)->format('H:i') : '23:59'" step="60" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">
                        Bonus (Rp)
                        <span class="ml-1 text-amber-600 cursor-help" title="Nilai rupiah remunerasi. Tidak dapat diisi bersamaan dengan poin.">!</span>
                    </label>
                    <x-ui.input type="number" step="0.01" name="bonus_amount" :value="$item->bonus_amount" x-model="bonus" x-bind:disabled="points && points > 0" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">
                        Poin
                        <span class="ml-1 text-amber-600 cursor-help" title="Poin kinerja sebagai alternatif bonus. Tidak dapat diisi bersamaan dengan bonus.">!</span>
                    </label>
                    <x-ui.input type="number" step="0.01" name="points" :value="$item->points" x-model="points" x-bind:disabled="bonus && bonus > 0" />
                </div>
                <div class="md:col-span-2 -mt-2">
                    <p class="text-xs text-slate-500">Isi salah satu: Bonus atau Poin. Semua waktu menggunakan zona Asia/Jakarta (UTC+7).</p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">File Pendukung (Word/Excel/PPT/PDF)</label>
                    <input type="file" name="supporting_file" accept=".doc,.docx,.xls,.xlsx,.ppt,.pptx,.pdf,application/pdf,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="block w-full text-sm text-slate-700 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100" />
                    @if($item->policy_doc_path)
                        <p class="mt-1 text-xs text-slate-500">File saat ini: <a href="{{ asset('storage/'.$item->policy_doc_path) }}" class="text-amber-600 hover:underline" target="_blank">Download</a></p>
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Maks. Klaim</label>
                    <x-ui.input type="number" name="max_claims" :value="$item->max_claims" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">
                        Batas Pembatalan (jam)
                        <span class="ml-1 text-amber-600 cursor-help" title="Batas waktu pembatalan klaim (jam) sejak klaim dibuat.">!</span>
                    </label>
                    <x-ui.input type="number" name="cancel_window_hours" x-model.number="cancelWindow" min="0" max="720" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">
                        Jenis Sanksi Default
                        <span class="ml-1 text-amber-600 cursor-help" title="Tidak ada: tanpa sanksi. Persentase: memotong persentase dari nilai dasar. Nominal: memotong nilai rupiah tetap.">!</span>
                    </label>
                    <x-ui.select
                        name="default_penalty_type"
                        x-model="penaltyType"
                        :options="[
                            'none' => 'Tidak ada',
                            'percent' => 'Persentase',
                            'amount' => 'Nominal',
                        ]"
                    />
                </div>

                <div x-show="penaltyType !== 'none'" x-cloak>
                    <label class="block text-sm font-medium text-slate-600 mb-1">
                        Nilai Sanksi Default
                        <span class="ml-1 text-amber-600 cursor-help" title="Jika Persentase: isi 0–100. Jika Nominal: isi nilai rupiah yang dipotong.">!</span>
                    </label>
                    <x-ui.input
                        type="number"
                        step="0.01"
                        name="default_penalty_value"
                        x-model.number="penaltyValue"
                        x-bind:disabled="penaltyType === 'none'"
                        x-bind:min="0"
                        x-bind:max="penaltyType === 'percent' ? 100 : null"
                    />
                    <p class="mt-1 text-xs text-slate-500" x-show="penaltyType === 'percent'">Range: 0–100</p>
                    <p class="mt-1 text-xs text-slate-500" x-show="penaltyType === 'amount'">Minimal: 0</p>
                </div>

                <div x-show="penaltyType === 'percent'" x-cloak>
                    <label class="block text-sm font-medium text-slate-600 mb-1">
                        Dasar Perhitungan Sanksi
                        <span class="ml-1 text-amber-600 cursor-help" title="Dipakai hanya untuk sanksi Persentase: potongan dihitung dari Bonus Tugas atau dari Remunerasi.">!</span>
                    </label>
                    <x-ui.select
                        name="penalty_base"
                        x-model="penaltyBase"
                        x-bind:disabled="penaltyType !== 'percent'"
                        :options="[
                            'task_bonus' => 'Bonus Tugas',
                            'remuneration' => 'Remunerasi',
                        ]"
                    />
                </div>

                <input type="hidden" name="default_penalty_value" value="0" x-bind:disabled="penaltyType !== 'none'">
                <input type="hidden" name="penalty_base" x-bind:value="penaltyBase" x-bind:disabled="penaltyType === 'percent'">
                <div class="md:col-span-2 flex items-center justify-between pt-2">
                    <x-ui.button as="a" href="{{ route('kepala_unit.additional-tasks.index') }}" variant="outline">
                        <i class="fa-solid fa-arrow-left"></i> Kembali
                    </x-ui.button>
                    <x-ui.button type="submit" variant="orange" class="h-12 px-6 text-base">
                        <i class="fa-solid fa-floppy-disk"></i> Simpan
                    </x-ui.button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            @php
                $pt = (string)($item->default_penalty_type ?? 'none');
                $pv = (float)($item->default_penalty_value ?? 0);
                $pb = (string)($item->penalty_base ?? 'task_bonus');
                if ($pt === 'none') {
                    $snap = 'Tidak ada sanksi.';
                } elseif ($pt === 'amount') {
                    $snap = 'Potong Rp ' . number_format($pv, 0, ',', '.') . '.';
                } else {
                    $baseLbl = $pb === 'remuneration' ? 'remunerasi' : 'bonus tugas';
                    $snap = rtrim(rtrim(number_format($pv, 2, ',', '.'), '0'), ',') . '% dari ' . $baseLbl . '.';
                }
            @endphp
            <div class="text-sm text-slate-600">
                <div class="font-semibold text-slate-800">Ringkasan Aturan</div>
                <div class="mt-1">Batas pembatalan: <span class="font-medium text-slate-800">{{ (int)($item->cancel_window_hours ?? 24) }} jam</span></div>
                <div>Aturan sanksi: <span class="font-medium text-slate-800">{{ $snap }}</span></div>
            </div>
        </div>
    </div>
</x-app-layout>
