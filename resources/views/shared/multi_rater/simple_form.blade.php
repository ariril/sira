@props([
    'title' => 'Penilaian 360°',
    'periodId',
    'windowId' => null,
    'unitId' => null,
    'raterRole',
    'targets' => [],
    'criteriaOptions' => [],
    'postRoute',
    'remainingAssignments' => null,
    'totalAssignments' => null,
    'buttonClasses' => 'bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 shadow-sm text-white',
    'savedTableKey' => null,
    'canSubmit' => true,
])

@php
    $targetsCollection = $targets instanceof \Illuminate\Support\Collection ? $targets : collect($targets);
    $criteriaCollection = $criteriaOptions instanceof \Illuminate\Support\Collection ? $criteriaOptions : collect($criteriaOptions);
    $targetsData = $targetsCollection->values()->all();
    $criteriaData = $criteriaCollection->values()->all();
    $remainingPeople = $targetsCollection->count();
@endphp

<div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100" x-data="simple360Form(@js($targetsData), @js($criteriaData), '{{ route($postRoute) }}', {{ (int)$periodId }}, {{ $windowId ? (int)$windowId : 'null' }}, {{ $unitId ? (int)$unitId : 'null' }}, '{{ $raterRole }}', '{{ $savedTableKey ?? '' }}', {{ (int) ($remainingAssignments ?? 0) }}, {{ (int) ($totalAssignments ?? 0) }}, {{ (int) $remainingPeople }}, {{ $canSubmit ? 'true' : 'false' }})">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">{{ $title }}</h2>
        </div>

        <template x-if="totalAssignments === 0">
            <div class="mb-3 p-4 rounded-xl bg-slate-50 border border-slate-200 text-slate-700 text-sm">
                Belum ada kriteria aktif untuk periode ini sehingga penilaian sederhana belum tersedia.
            </div>
        </template>
        <template x-if="totalAssignments > 0 && remainingAssignments === 0">
            <div class="mb-3 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
                Semua penilaian pada periode ini telah selesai.
            </div>
        </template>
        <template x-if="totalAssignments > 0 && remainingAssignments > 0">
            <div class="mb-3 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                <span x-text="progressMessage()"></span>
            </div>
        </template>

        <template x-if="success">
            <div class="mb-3 text-sm text-emerald-700 bg-emerald-50 border border-emerald-100 px-3 py-2 rounded">
                Nilai berhasil disimpan.
            </div>
        </template>

        <template x-if="!canSubmit">
            <div class="mb-3 p-4 rounded-xl bg-slate-50 border border-slate-200 text-slate-600 text-sm">
                Penilaian 360 hanya dapat diisi ketika periode berstatus ACTIVE.
            </div>
        </template>

        <div class="grid gap-4 sm:grid-cols-12" x-show="canSubmit" x-cloak>
            <div class="sm:col-span-6">
                <div class="flex items-center gap-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Target Dinilai (Nama / NIP)</label>
                    <span class="inline-flex items-center justify-center h-5 w-5 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold" title="Badge menunjukkan posisi Anda sebagai penilai terhadap target (mis. Anda Atasan L1).">!</span>
                </div>
                <div class="relative" @click.away="targetDropdown = false; maybeAutoSelectTarget()">
                    <x-ui.input type="text" placeholder="Contoh: dr. Charles / 10.00…" x-model="targetQuery" @focus="openTargetDropdown(false)" @input="openTargetDropdown(true)" @keydown.enter.prevent="maybeAutoSelectTarget(); targetDropdown = false" class="pr-12" />
                    <button type="button" class="absolute right-11 top-1/2 -translate-y-1/2 text-slate-400" x-show="selectedTargetId" @click="clearTarget()">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <i class="fa-solid fa-chevron-down pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <div x-show="targetDropdown" x-cloak class="absolute z-20 mt-2 w-full rounded-2xl border border-slate-200 bg-white shadow-xl max-h-64 overflow-y-auto">
                        <template x-if="filteredTargets().length === 0">
                            <div class="px-4 py-3 text-sm text-slate-500">Target tidak ditemukan.</div>
                        </template>
                        <template x-for="person in filteredTargets()" :key="person.id">
                            <button type="button" class="w-full text-left px-4 py-3 text-sm hover:bg-amber-50" @mousedown.prevent="selectTarget(person)" @click.prevent>
                                <span class="font-medium" x-text="person.label ?? person.name"></span>
                                <template x-if="person.employee_number">
                                    <span class="text-slate-500" x-text="' • ' + person.employee_number"></span>
                                </template>
                                <span class="ml-2 inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold" :class="rolePillClasses(person)" x-text="roleLabel(person)"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
            <div class="sm:col-span-6">
                <div class="flex items-center gap-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Kriteria</label>
                    <span class="inline-flex items-center justify-center h-5 w-5 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold" title="Benefit: nilai tinggi semakin baik. Cost: nilai rendah semakin baik.">!</span>
                </div>
                <div x-ref="criteriaSelectWrapper">
                    <x-ui.select :options="[]" placeholder="Pilih kriteria" x-bind:class="applyAll ? 'opacity-60' : ''" x-bind:disabled="applyAll" x-on:change="onCriteriaChange($event.target.value)" />
                </div>
                <label class="mt-2 text-xs text-slate-600 inline-flex items-center gap-2">
                    <input type="checkbox" class="h-4 w-4 rounded border-slate-300" x-model="applyAll" @change="handleApplyAllToggle()">
                    Berlaku untuk semua kriteria
                </label>
            </div>
            <div class="sm:col-span-3">
                <label class="block text-sm font-medium text-slate-700 mb-1">Nilai</label>
                <x-ui.input type="number" min="1" max="100" x-model="score" placeholder="Masukkan nilai (1–100)" />
            </div>
            <div class="sm:col-span-3 flex items-end">
                <button type="button" @click="submitIfValid()" class="inline-flex items-center justify-center h-12 px-6 rounded-xl text-[15px] font-medium {{ $buttonClasses }} disabled:opacity-50 disabled:cursor-not-allowed" :disabled="submitting">
                    Simpan
                </button>
            </div>
        </div>

        <div class="mt-3 text-sm text-slate-500">
            Nilai yang disimpan per kriteria akan otomatis hilang dari daftar hingga seluruh kriteria tiap target terpenuhi.
        </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('simple360Form', (initialItems, criteriaCatalog, postUrl, periodId, windowId, unitId, raterRole, savedTableKey = '', remainingAssignments = 0, totalAssignments = 0, remainingPeople = 0, canSubmit = true) => ({
                items: initialItems,
                criteriaCatalog,
                criteriaMap: {},
                targetQuery: '',
                selectedTargetId: '',
                targetDropdown: false,
                selectedCriteriaId: '',
                applyAll: false,
                score: '',
                success: false,
                savedTableKey: savedTableKey || null,
                remainingAssignments: Number(remainingAssignments) || 0,
                totalAssignments: Number(totalAssignments) || 0,
                remainingPeople: Number(remainingPeople) || 0,
                canSubmit: !!canSubmit,
                submitting: false,
                init() {
                    this.criteriaMap = this.criteriaCatalog.reduce((acc, item) => {
                        acc[item.id] = item;
                        return acc;
                    }, {});
                    this.refreshCriteriaSelect();
                },
                roleLabel(person) {
                    const type = String(person?.assessor_type ?? 'peer');
                    const lvlRaw = person?.assessor_level;
                    const lvl = Number.isFinite(Number(lvlRaw)) ? Number(lvlRaw) : 0;
                    if (type === 'supervisor') {
                        return `Atasan L${(lvl && lvl > 0) ? Math.trunc(lvl) : 1}`;
                    }
                    if (type === 'self') return 'Diri';
                    if (type === 'peer') return 'Rekan';
                    if (type === 'subordinate') return 'Bawahan';
                    return type;
                },
                rolePillClasses(person) {
                    const type = String(person?.assessor_type ?? 'peer');
                    if (type === 'supervisor') return 'bg-amber-50 text-amber-800 border-amber-200';
                    if (type === 'self') return 'bg-slate-50 text-slate-700 border-slate-200';
                    if (type === 'peer') return 'bg-sky-50 text-sky-800 border-sky-200';
                    if (type === 'subordinate') return 'bg-emerald-50 text-emerald-800 border-emerald-200';
                    return 'bg-slate-50 text-slate-700 border-slate-200';
                },
                progressMessage() {
                    return `Tersisa ${this.remainingPeople} Orang dengan ${this.remainingAssignments} penilaian kriteria yang belum diisi.`;
                },
                criteriaSelect() { return this.$refs.criteriaSelectWrapper?.querySelector('select'); },
                normalizeText(value) { return String(value ?? '').trim().toLowerCase(); },
                maybeAutoSelectTarget() {
                    if (this.selectedTargetId) return;
                    const term = this.normalizeText(this.targetQuery);
                    if (!term) return;
                    const matches = this.filteredTargets();
                    if (matches.length === 1) {
                        this.selectTarget(matches[0]);
                    }
                },
                openTargetDropdown(clearSelection = false) {
                    this.targetDropdown = true;
                    // Only clear the current selection if the user actually types/edits the field.
                    if (clearSelection && this.selectedTargetId) {
                        this.selectedTargetId = '';
                        this.selectedCriteriaId = '';
                        this.refreshCriteriaSelect();
                    }
                },
                filteredTargets() {
                    const term = this.normalizeText(this.targetQuery);
                    if (!term) return this.items;
                    return this.items.filter(item => {
                        const haystack = this.normalizeText([
                            item.label,
                            item.name,
                            item.employee_number,
                            item.searchable,
                        ].filter(Boolean).join(' '));
                        return haystack.includes(term);
                    });
                },
                selectTarget(person) {
                    this.selectedTargetId = String(person.id);
                    this.targetQuery = String(person.label ?? person.name ?? '');
                    this.targetDropdown = false;
                    this.selectedCriteriaId = '';
                    this.refreshCriteriaSelect();
                },
                clearTarget() {
                    this.selectedTargetId = '';
                    this.targetQuery = '';
                    this.targetDropdown = false;
                    this.selectedCriteriaId = '';
                    this.refreshCriteriaSelect();
                },
                refreshCriteriaSelect() {
                    const sel = this.criteriaSelect();
                    if (!sel) return;
                    sel.innerHTML = '';
                    const opt0 = document.createElement('option');
                    opt0.value = '';
                    opt0.textContent = this.applyAll ? 'Semua kriteria terpilih' : 'Pilih kriteria';
                    sel.appendChild(opt0);

                    if (this.applyAll) {
                        this.selectedCriteriaId = '';
                        return;
                    }

                    const target = this.items.find(item => String(item.id) === String(this.selectedTargetId));
                    if (!target) {
                        this.selectedCriteriaId = '';
                        return;
                    }
                    let hasSelection = false;
                    target.pending_criteria.forEach(id => {
                        const crt = this.criteriaMap[id];
                        if (!crt) return;
                        const option = document.createElement('option');
                        option.value = id;
                        option.textContent = `${crt.name} • ${crt.type_label}`;
                        sel.appendChild(option);
                        if (String(id) === String(this.selectedCriteriaId)) {
                            hasSelection = true;
                        }
                    });
                    if (this.selectedCriteriaId && hasSelection) {
                        sel.value = this.selectedCriteriaId;
                    } else {
                        this.selectedCriteriaId = '';
                        sel.value = '';
                    }
                },
                handleApplyAllToggle() {
                    if (this.applyAll) {
                        this.selectedCriteriaId = '';
                    }
                    this.refreshCriteriaSelect();
                },
                onCriteriaChange(value) {
                    this.selectedCriteriaId = value;
                },
                submitIfValid() {
                    if (!this.canSubmit) {
                        alert('Penilaian 360 hanya dapat diisi ketika periode berstatus ACTIVE.');
                        return;
                    }
                    if (!this.selectedTargetId) {
                        alert('Pilih rekan terlebih dahulu.');
                        return;
                    }
                    if (!this.applyAll && !this.selectedCriteriaId) {
                        alert('Pilih kriteria yang akan dinilai.');
                        return;
                    }
                    if (!this.score || this.score < 1 || this.score > 100) {
                        alert('Isikan nilai antara 1 sampai 100.');
                        return;
                    }

                    const targetInfo = this.items.find(item => String(item.id) === String(this.selectedTargetId));
                    const targetSnapshot = targetInfo ? { ...targetInfo } : null;
                    const previousPending = targetInfo ? [...targetInfo.pending_criteria] : [];
                    const submittedScore = this.score;
                    this.submitting = true;

                    const fd = new FormData();
                    fd.set('assessment_period_id', periodId);
                    // Window id is not persisted on multi_rater_assessments; use period only.
                    if (unitId) fd.set('unit_id', unitId);
                    fd.set('rater_role', raterRole);
                    fd.set('target_user_id', this.selectedTargetId);
                    fd.set('score', this.score);
                    if (this.applyAll) {
                        fd.set('apply_all', '1');
                    } else {
                        fd.set('performance_criteria_id', this.selectedCriteriaId);
                    }

                    fetch(postUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
                        .then(async response => {
                            if (!response.ok) {
                                let message = 'Gagal menyimpan nilai.';
                                try { const data = await response.json(); message = data.message || message; } catch (e) {}
                                throw new Error(message);
                            }
                            return response.json();
                        })
                        .then(data => {
                            const pendingIds = Array.isArray(data.pending) ? data.pending.map(id => Number(id)) : [];
                            const filledIds = Array.isArray(data.filled) ? data.filled.map(id => Number(id)) : [];
                            const completedIds = this.resolveCompletedIds(previousPending, pendingIds, filledIds);
                            const targetCleared = this.updateTargetPending(pendingIds);
                            this.updateCounters(completedIds, targetCleared);
                            this.emitSavedRows(completedIds, targetSnapshot, submittedScore);
                            this.score = '';
                            this.success = true;
                            setTimeout(() => this.success = false, 3000);
                        })
                        .catch(err => alert(err.message))
                        .finally(() => { this.submitting = false; });
                },
                resolveCompletedIds(previousPending, newPending, filled) {
                    const prev = previousPending.map(id => Number(id));
                    const fallback = prev.filter(id => !newPending.includes(id));
                    const base = filled.length ? filled : fallback;
                    const unique = [];
                    base.forEach(id => {
                        if (prev.includes(id) && !unique.includes(id)) {
                            unique.push(id);
                        }
                    });
                    return unique;
                },
                updateCounters(completedIds, targetCleared) {
                    if (completedIds.length > 0) {
                        this.remainingAssignments = Math.max(0, this.remainingAssignments - completedIds.length);
                    }
                    if (targetCleared && this.remainingPeople > 0) {
                        this.remainingPeople -= 1;
                    }
                },
                emitSavedRows(completedIds, targetSnapshot, submittedScore) {
                    if (!this.savedTableKey || !targetSnapshot || !completedIds.length) return;
                    const roleLabel = this.roleLabel(targetSnapshot);
                    const roleClass = this.rolePillClasses(targetSnapshot);
                    const rows = completedIds.map(id => {
                        const info = this.criteriaMap[id] || {};
                        return {
                            tableKey: this.savedTableKey,
                            targetId: targetSnapshot.id,
                            targetName: targetSnapshot.name || targetSnapshot.label,
                            assessorType: targetSnapshot.assessor_type ?? null,
                            assessorLevel: targetSnapshot.assessor_level ?? null,
                            roleLabel,
                            roleClass,
                            criteriaId: id,
                            criteriaName: info.name || 'Kriteria',
                            criteriaType: info.type || 'benefit',
                            criteriaTypeLabel: info.type_label || 'Benefit',
                            score: submittedScore,
                            timestamp: new Date().toISOString(),
                        };
                    });
                    window.dispatchEvent(new CustomEvent('multi-rater-score-added', { detail: rows }));
                },
                updateTargetPending(pendingIds) {
                    const targetIndex = this.items.findIndex(item => String(item.id) === String(this.selectedTargetId));
                    if (targetIndex === -1) {
                        this.selectedTargetId = '';
                        this.targetQuery = '';
                        this.targetDropdown = false;
                        this.refreshCriteriaSelect();
                        return false;
                    }
                    this.items[targetIndex].pending_criteria = pendingIds;
                    const cleared = pendingIds.length === 0;
                    if (cleared) {
                        this.items.splice(targetIndex, 1);
                        this.selectedTargetId = '';
                        this.targetQuery = '';
                        this.targetDropdown = false;
                    }
                    this.applyAll = false;
                    this.selectedCriteriaId = '';
                    this.refreshCriteriaSelect();
                    return cleared;
                },
            }))
        });

        if (!window.__multiRaterSavedTableListener) {
            window.__multiRaterSavedTableListener = true;
            window.addEventListener('multi-rater-score-added', event => {
                const rows = Array.isArray(event.detail) ? event.detail : [];
                rows.forEach(row => {
                    const wrapper = document.querySelector(`[data-saved-table-key="${row.tableKey}"]`);
                    if (!wrapper) return;
                    const tbody = wrapper.querySelector('tbody');
                    if (!tbody) return;
                    const badgeClass = row.criteriaType === 'cost'
                        ? 'bg-rose-50 text-rose-700 border-rose-200'
                        : 'bg-emerald-50 text-emerald-700 border-emerald-200';
                    const typeLabel = row.criteriaTypeLabel || (row.criteriaType === 'cost' ? 'Cost' : 'Benefit');
                    const timeLabel = formatTimestamp(row.timestamp);
                    const allowEdit = wrapper.dataset.allowInlineEdit === 'true';
                    const editUrl = wrapper.dataset.editUrl;
                    const csrf = wrapper.dataset.csrf;
                    const periodId = wrapper.dataset.periodId;
                    const windowId = wrapper.dataset.windowId;
                    const inlineVariant = wrapper.dataset.inlineVariant || 'orange';
                    let scoreCell = `<span class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700">${row.score}</span>`;
                    if (allowEdit && editUrl && csrf && periodId) {
                        scoreCell = buildInlineEditForm({ editUrl, csrf, periodId, windowId, targetId: row.targetId, criteriaId: row.criteriaId, score: row.score, variant: inlineVariant });
                    }

                    const roleLabel = String(row.roleLabel ?? '').trim();
                    const roleClass = String(row.roleClass ?? '').trim() || 'bg-slate-50 text-slate-700 border-slate-200';
                    const roleCell = roleLabel
                        ? `<span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold ${roleClass}">${roleLabel}</span>`
                        : `<span class="text-xs text-slate-500">-</span>`;

                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-slate-50';
                    tr.innerHTML = `
                        <td class="px-6 py-4">${row.targetName}</td>
                        <td class="px-6 py-4">${row.criteriaName}</td>
                        <td class="px-6 py-4">${roleCell}</td>
                        <td class="px-6 py-4"><span class="px-2 py-1 rounded text-xs border ${badgeClass}">${typeLabel}</span></td>
                        <td class="px-6 py-3">${scoreCell}</td>
                        <td class="px-6 py-4 text-slate-600">${timeLabel}</td>
                    `;
                    tbody.prepend(tr);

                    const emptyState = wrapper.querySelector('[data-saved-empty]');
                    if (emptyState) {
                        emptyState.classList.add('hidden');
                    }
                });
            });

            function formatTimestamp(source) {
                const date = source ? new Date(source) : new Date();
                try {
                    return new Intl.DateTimeFormat('id-ID', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    }).format(date);
                } catch (e) {
                    return date.toLocaleString();
                }
            }

            function buildInlineEditForm({ editUrl, csrf, periodId, windowId, targetId, criteriaId, score, variant = 'orange' }) {
                let buttonColor;
                switch (variant) {
                    case 'sky':
                        buttonColor = 'text-white bg-gradient-to-tr from-sky-400 to-blue-500 hover:brightness-110 focus:ring-sky-400';
                        break;
                    case 'violet':
                        buttonColor = 'text-white bg-gradient-to-tr from-fuchsia-500 to-violet-600 hover:brightness-110 focus:ring-fuchsia-500';
                        break;
                    default:
                        buttonColor = 'text-white bg-gradient-to-tr from-amber-500 to-orange-600 hover:brightness-110 focus:ring-amber-500';
                        break;
                }
                const buttonBase = 'inline-flex items-center justify-center gap-2 font-medium rounded-xl shadow-sm transition select-none focus:outline-none focus:ring-2 focus:ring-offset-1';
                const buttonClass = `${buttonBase} ${buttonColor} h-10 px-4 text-sm font-semibold`;
                const inputBase = 'w-full h-12 pl-4 pr-4 rounded-xl border-slate-300 text-[15px] shadow-sm focus:border-blue-500 focus:ring-blue-500';
                const inputClass = `${inputBase} h-10 text-sm text-right`;
                return `
<form class="inline-flex items-center gap-2" onsubmit="event.preventDefault(); const fd=new FormData(this); fetch('${editUrl}',{method:'POST',headers:{'X-CSRF-TOKEN':'${csrf}','Accept':'application/json'},body:fd}).then(r=>r.json()).then(()=>location.reload());">
    <input type="hidden" name="assessment_period_id" value="${periodId}">

    <input type="hidden" name="target_user_id" value="${targetId}">
    <input type="hidden" name="performance_criteria_id" value="${criteriaId}">
    <div class="relative w-24">
        <input type="number" min="1" max="100" name="score" value="${score}" class="${inputClass}" />
    </div>
    <button type="submit" class="${buttonClass}">Ubah</button>
</form>`;
            }
        }
    </script>
</div>
