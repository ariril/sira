<x-app-layout title="Edit Alokasi Unit">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Edit Alokasi</h1>
            <x-ui.button
                as="a"
                href="{{ route('admin_rs.unit-remuneration-allocations.index') }}"
                variant="outline"
                class="h-12 px-6 text-base"
            >
                Kembali
            </x-ui.button>
        </div>
    </x-slot>

    @php($isReadOnly = !empty($item->published_at))

    <div class="container-px py-6 space-y-6">
        @php($periodError = $errors->first('assessment_period_id'))

        @if ($periodError)
            <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm flex items-start gap-2">
                <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                <span>Anda wajib memilih periode.</span>
            </div>
        @endif

        @if (session('danger'))
            <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm flex items-start gap-2">
                <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                <span>{{ session('danger') }}</span>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <form method="POST" action="{{ route('admin_rs.unit-remuneration-allocations.update', $item) }}" class="space-y-6" id="alloc-form">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    @if ($isReadOnly)
                        <div class="grid md:grid-cols-3 gap-4">
                            <div class="space-y-1">
                                <span class="text-sm font-medium text-slate-700">Periode</span>
                                <div class="h-11 flex items-center px-3 rounded-lg border border-slate-200 text-sm text-slate-700 bg-slate-50">
                                    <span class="font-semibold">{{ $item->period->name ?? '-' }}</span>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <span class="text-sm font-medium text-slate-700">Unit</span>
                                <div class="h-11 flex items-center px-3 rounded-lg border border-slate-200 text-sm text-slate-700 bg-slate-50">
                                    <span class="font-semibold">{{ $item->unit->name ?? '-' }}</span>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <span class="text-sm font-medium text-slate-700">Total Alokasi</span>
                                <div class="h-11 flex items-center px-3 rounded-lg border border-slate-200 text-sm text-slate-700 bg-slate-50">
                                    <span id="amount-hint" class="font-semibold">Rp {{ number_format($item->amount ?? 0, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="grid md:grid-cols-3 gap-4">
                            <label class="space-y-1">
                                <span class="text-sm font-medium text-slate-700">Periode</span>
                                <x-ui.select
                                    name="assessment_period_id"
                                    :options="$periods->pluck('name', 'id')"
                                    :value="old('assessment_period_id', $item->assessment_period_id)"
                                    placeholder="Pilih periode"
                                    class="h-11"
                                />
                            </label>

                            <label class="space-y-1">
                                <span class="text-sm font-medium text-slate-700">Unit</span>
                                <x-ui.select
                                    name="unit_id"
                                    :options="$units->pluck('name', 'id')"
                                    :value="old('unit_id', $item->unit_id)"
                                    placeholder="Pilih unit"
                                    id="unit-select"
                                    class="h-11"
                                />
                            </label>

                            <div class="space-y-1">
                                <span class="text-sm font-medium text-slate-700">Total Alokasi</span>
                                <div class="h-11 flex items-center px-3 rounded-lg border border-slate-200 text-sm text-slate-700 bg-slate-50">
                                    <span id="amount-hint" class="font-semibold">Rp {{ number_format($item->amount ?? 0, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Catatan</label>
                        <x-ui.textarea
                            name="note"
                            rows="3"
                            value="{{ old('note', $item->note) }}"
                            placeholder="Opsional"
                            :disabled="$isReadOnly"
                        />
                    </div>

                    <div class="border border-slate-200 rounded-xl p-4 space-y-3" id="prof-section">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-semibold text-slate-800">Profesi pada Unit</div>
                                <div class="text-xs text-slate-500">Isi nominal per profesi</div>
                            </div>
                            <div class="text-xs text-slate-600">
                                Total:
                                @if ($isReadOnly)
                                    <span>Rp {{ number_format((float) ($item->amount ?? 0), 0, ',', '.') }}</span>
                                @else
                                    <span id="lines-total">Rp 0</span>
                                @endif
                            </div>
                        </div>

                        @if ($isReadOnly)
                            @php($lines = $lines ?? collect())

                            @if ($lines->count() > 0)
                                <div class="grid md:grid-cols-2 gap-3">
                                    @foreach ($lines as $ln)
                                        <div class="grid grid-cols-2 gap-3 items-center rounded-lg border border-slate-200 px-3 py-2 bg-white">
                                            <div class="space-y-1 min-w-0">
                                                <div class="text-sm text-slate-800 truncate">{{ $ln->profession->name ?? 'Profesi' }}</div>
                                                <div class="text-xs text-slate-500">Nominal</div>
                                            </div>
                                            <div class="h-11 w-full flex items-center justify-end rounded-lg border border-slate-200 px-3 text-sm bg-slate-50">
                                                <span class="font-semibold">Rp {{ number_format((float) ($ln->amount ?? 0), 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-xs text-amber-600">Tidak ada data profesi pada alokasi ini.</div>
                            @endif
                        @else
                            <div class="grid md:grid-cols-2 gap-3" id="prof-list"></div>
                            <div class="text-xs text-amber-600 hidden" id="prof-empty">Tidak ada profesi pada unit ini.</div>
                        @endif
                    </div>

                    @if ($isReadOnly)
                        <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm flex items-start gap-2">
                            <i class="fa-solid fa-circle-check mt-0.5"></i>
                            <div class="min-w-0">
                                <div class="font-medium">Alokasi sudah Published (read-only).</div>
                                <div class="text-xs text-emerald-700 mt-0.5">
                                    @if (!empty($item->published_at))
                                        Dipublish: {{ optional($item->published_at)->format('d M Y H:i') }}.
                                    @endif
                                    Data tidak bisa diubah lagi.
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-3">
                            <x-ui.input type="checkbox" name="publish_now" value="1" class="h-5 w-5" />
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-slate-700">Publish sekarang</div>
                                <div class="text-xs text-slate-500">Jika dicentang, status menjadi Published dan tidak bisa diubah lagi.</div>
                            </div>
                        </div>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if (!$isReadOnly)
        <script>
            window.__ALLOC_EDIT__ = {
                professions: @json($professions->keyBy('id')),
                unitMap: @json($unitProfessionMap->map->pluck('profession_id')->toArray()),
                existing: @json($lineMap ?? []),
            };
        </script>

        <script>
            (() => {
                const profListEl = document.getElementById('prof-list');
                const unitSelect = document.getElementById('unit-select');
                const totalEl = document.getElementById('lines-total');
                const amountHint = document.getElementById('amount-hint');
                const emptyEl = document.getElementById('prof-empty');

                const data = window.__ALLOC_EDIT__ || {};
                const professions = data.professions || {};
                const unitMap = data.unitMap || {};
                const existing = data.existing || {};

                const formatRp = (n) =>
                    new Intl.NumberFormat('id-ID', {
                        style: 'currency',
                        currency: 'IDR',
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 2,
                    }).format(n || 0);

                const recalc = () => {
                    const inputs = profListEl ? profListEl.querySelectorAll('input[data-lines-input]') : [];
                    let sum = 0;

                    inputs.forEach((el) => {
                        sum += parseFloat(el.value) || 0;
                    });

                    if (totalEl) totalEl.textContent = formatRp(sum);
                    if (amountHint) amountHint.textContent = formatRp(sum);
                };

                const renderRows = (unitId) => {
                    if (!profListEl) return;

                    profListEl.innerHTML = '';
                    const profIds = unitMap[unitId] || [];

                    if (!profIds.length) {
                        if (emptyEl) emptyEl.classList.remove('hidden');
                        recalc();
                        return;
                    }

                    if (emptyEl) emptyEl.classList.add('hidden');

                    profIds.forEach((pid) => {
                        const p = professions[pid];
                        if (!p) return;

                        const wrapper = document.createElement('label');
                        wrapper.className = 'grid grid-cols-2 gap-3 items-center rounded-lg border border-slate-200 px-3 py-2';

                        wrapper.innerHTML =
                            '<div class="space-y-1">' +
                                '<div class="text-sm text-slate-800">' + (p.name || '') + '</div>' +
                                '<div class="text-xs text-slate-500 rupiah-hint">Rp 0</div>' +
                            '</div>' +
                            '<input type="number" step="0.01" min="0" name="lines[' + pid + ']" inputmode="decimal"' +
                                ' class="h-11 w-full text-right rounded-lg border border-slate-200 px-3 text-sm focus:border-slate-300 focus:ring-0"' +
                                ' data-lines-input />';

                        const input = wrapper.querySelector('input');
                        const hint = wrapper.querySelector('.rupiah-hint');

                        const preset = (existing && existing[pid] !== undefined) ? existing[pid] : null;
                        if (preset !== null) {
                            input.value = preset;
                            hint.textContent = formatRp(parseFloat(preset) || 0);
                        }

                        input.addEventListener('input', () => {
                            hint.textContent = formatRp(parseFloat(input.value) || 0);
                            recalc();
                        });

                        profListEl.appendChild(wrapper);
                    });

                    recalc();
                };

                if (unitSelect) {
                    unitSelect.addEventListener('change', (e) => renderRows(e.target.value));
                    if (unitSelect.value) renderRows(unitSelect.value);
                }
            })();
        </script>
    @endif
</x-app-layout>
