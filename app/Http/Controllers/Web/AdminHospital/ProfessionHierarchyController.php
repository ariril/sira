<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\Profession;
use App\Models\ProfessionReportingLine;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfessionHierarchyController extends Controller
{
    private const RELATION_TYPES = [
        'supervisor' => 'Supervisor (Atasan)',
        'peer' => 'Peer (Rekan)',
        'subordinate' => 'Subordinate (Bawahan)',
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdministrasi(), 403);

        $filters = $request->validate([
            'assessee_profession_id' => ['nullable', 'integer'],
            'relation_type' => ['nullable', Rule::in(array_keys(self::RELATION_TYPES))],
            'is_active' => ['nullable', Rule::in(['1', '0'])],
        ]);

        $professions = Profession::query()->orderBy('name')->get(['id', 'name']);

        $q = ProfessionReportingLine::query()
            ->with([
                'assesseeProfession:id,name',
                'assessorProfession:id,name',
            ])
            ->when(!empty($filters['assessee_profession_id'] ?? null), fn($w) => $w->where('assessee_profession_id', (int) $filters['assessee_profession_id']))
            ->when(!empty($filters['relation_type'] ?? null), fn($w) => $w->where('relation_type', (string) $filters['relation_type']))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, fn($w) => $w->where('is_active', (int) $filters['is_active']))
            ->orderBy('assessee_profession_id')
            ->orderBy('relation_type')
            ->orderByRaw('CASE WHEN level IS NULL THEN 999999 ELSE level END ASC')
            ->orderBy('assessor_profession_id');

        $rows = $q->get();

        $grouped = $rows->groupBy('assessee_profession_id');

        return view('admin_rs.profession_hierarchy.index', [
            'professions' => $professions,
            'rows' => $rows,
            'grouped' => $grouped,
            'filters' => $filters,
            'relationTypes' => self::RELATION_TYPES,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->isAdministrasi(), 403);

        $professions = Profession::query()->orderBy('name')->get(['id', 'name']);

        return view('admin_rs.profession_hierarchy.form', [
            'mode' => 'create',
            'item' => new ProfessionReportingLine(['is_required' => true, 'is_active' => true, 'relation_type' => 'supervisor']),
            'professions' => $professions,
            'relationTypes' => self::RELATION_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdministrasi(), 403);

        $data = $this->validatePayload($request);

        try {
            ProfessionReportingLine::query()->create($data);
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return back()->withInput()->withErrors([
                    'assessee_profession_id' => $this->buildDuplicateMessage(
                        (int) $data['assessee_profession_id'],
                        (int) $data['assessor_profession_id'],
                        (string) $data['relation_type']
                    ),
                ]);
            }
            throw $e;
        }

        return redirect()->route('admin_rs.profession_hierarchy.index')->with('status', 'Aturan hirarki berhasil ditambahkan.');
    }

    public function edit(Request $request, ProfessionReportingLine $professionReportingLine): View
    {
        abort_unless($request->user()?->isAdministrasi(), 403);

        $professions = Profession::query()->orderBy('name')->get(['id', 'name']);

        return view('admin_rs.profession_hierarchy.form', [
            'mode' => 'edit',
            'item' => $professionReportingLine,
            'professions' => $professions,
            'relationTypes' => self::RELATION_TYPES,
        ]);
    }

    public function update(Request $request, ProfessionReportingLine $professionReportingLine): RedirectResponse
    {
        abort_unless($request->user()?->isAdministrasi(), 403);

        $data = $this->validatePayload($request, $professionReportingLine->id, $professionReportingLine);

        try {
            $professionReportingLine->fill($data);
            $professionReportingLine->save();
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return back()->withInput()->withErrors([
                    'assessee_profession_id' => $this->buildDuplicateMessage(
                        (int) $data['assessee_profession_id'],
                        (int) $data['assessor_profession_id'],
                        (string) $data['relation_type']
                    ),
                ]);
            }
            throw $e;
        }

        return redirect()->route('admin_rs.profession_hierarchy.index')->with('status', 'Aturan hirarki berhasil diperbarui.');
    }

    public function toggle(Request $request, ProfessionReportingLine $professionReportingLine): RedirectResponse
    {
        abort_unless($request->user()?->isAdministrasi(), 403);

        $professionReportingLine->is_active = !$professionReportingLine->is_active;
        $professionReportingLine->save();

        return back()->with('status', 'Status berhasil diubah.');
    }

    public function destroy(Request $request, ProfessionReportingLine $professionReportingLine): RedirectResponse
    {
        abort_unless($request->user()?->isAdministrasi(), 403);

        $professionReportingLine->delete();

        return back()->with('status', 'Aturan berhasil dihapus.');
    }

    private function validatePayload(Request $request, ?int $ignoreId = null, ?ProfessionReportingLine $existing = null): array
    {
        $relationType = (string) $request->input('relation_type', 'supervisor');
        $isLevelRelation = in_array($relationType, ['supervisor', 'subordinate'], true);

        $assesseeId = (int) $request->input('assessee_profession_id');
        $levelCount = 0;
        if ($isLevelRelation && $assesseeId > 0) {
            $levelCount = ProfessionReportingLine::query()
                ->where('assessee_profession_id', $assesseeId)
                ->where('relation_type', $relationType)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->count();
        }

        $levelRules = ['nullable', 'integer', 'min:1'];
        if ($isLevelRelation && $levelCount >= 1) {
            $levelRules[] = 'required';
        }

        if ($isLevelRelation && $request->filled('level')) {
            $uniqueLevel = Rule::unique('profession_reporting_lines', 'level')
                ->where(fn($q) => $q
                    ->where('assessee_profession_id', $assesseeId)
                    ->where('relation_type', $relationType)
                );
            if ($ignoreId) {
                $uniqueLevel = $uniqueLevel->ignore($ignoreId);
            }
            $levelRules[] = $uniqueLevel;
        }

        $validated = $request->validate([
            'assessee_profession_id' => ['required', 'integer', 'different:assessor_profession_id', 'exists:professions,id'],
            'assessor_profession_id' => ['required', 'integer', 'different:assessee_profession_id', 'exists:professions,id'],
            'relation_type' => ['required', Rule::in(array_keys(self::RELATION_TYPES))],
            'level' => $levelRules,
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'assessee_profession_id.different' => 'Profesi Dinilai dan Profesi Penilai tidak boleh sama.',
            'assessor_profession_id.different' => 'Profesi Dinilai dan Profesi Penilai tidak boleh sama.',
        ]);

        $assesseeId = (int) ($validated['assessee_profession_id'] ?? 0);
        $assessorId = (int) ($validated['assessor_profession_id'] ?? 0);
        if ($assesseeId > 0 && $assessorId > 0) {
            $duplicate = ProfessionReportingLine::query()
                ->where('assessee_profession_id', $assesseeId)
                ->where('assessor_profession_id', $assessorId)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->first();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'assessee_profession_id' => $this->buildDuplicateMessage(
                        $assesseeId,
                        $assessorId,
                        (string) $duplicate->relation_type
                    ),
                ]);
            }
        }

        // Normalize booleans (checkboxes)
        // If the UI no longer sends is_required, preserve existing value on update; default true on create.
        $validated['is_required'] = $request->has('is_required')
            ? (bool) ($request->boolean('is_required'))
            : (bool) ($existing?->is_required ?? true);
        $validated['is_active'] = (bool) ($request->boolean('is_active'));

        // Enforce level null unless supervisor/subordinate (defensive)
        if (!in_array(($validated['relation_type'] ?? null), ['supervisor', 'subordinate'], true)) {
            $validated['level'] = null;
        }

        return $validated;
    }

    private function buildDuplicateMessage(int $assesseeId, int $assessorId, string $relationType): string
    {
        $assesseeName = (string) (Profession::query()->whereKey($assesseeId)->value('name') ?? 'Profesi Dinilai');
        $assessorName = (string) (Profession::query()->whereKey($assessorId)->value('name') ?? 'Profesi Penilai');
        $relationLabel = self::RELATION_TYPES[$relationType] ?? $relationType;

        return "Profesi Dinilai {$assesseeName} dan Profesi Penilai {$assessorName} telah terhubung dengan relasi {$relationLabel}.";
    }
}
