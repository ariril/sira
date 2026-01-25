<?php

namespace App\Services\Assessment360;

use App\Models\Assessment360Window;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Assessment360WindowService
{

    public function checkCompleteness(Assessment360Window $window): array
    {
        $periodId = (int) ($window->assessment_period_id ?? 0);
        if ($periodId <= 0) {
            return [
                'is_complete' => false,
                'missing_count' => 0,
                'missing_count_total' => 0,
                'missing_details' => [],
                'missing_by_assessee' => [],
                'message' => 'Periode penilaian tidak valid.',
            ];
        }

        if (!Schema::hasTable('multi_rater_assessments') || !Schema::hasTable('multi_rater_assessment_details')) {
            return [
                'is_complete' => false,
                'missing_count' => 0,
                'missing_count_total' => 0,
                'missing_details' => [],
                'missing_by_assessee' => [],
                'message' => 'Data Penilaian 360 belum tersedia.',
            ];
        }

        $hasSnapshot = Schema::hasTable('assessment_period_user_membership_snapshots')
            && DB::table('assessment_period_user_membership_snapshots')
                ->where('assessment_period_id', $periodId)
                ->exists();

        if ($hasSnapshot) {
            $assessees = DB::table('assessment_period_user_membership_snapshots as ms')
                ->join('users as u', 'u.id', '=', 'ms.user_id')
                ->leftJoin('units as un', 'un.id', '=', 'ms.unit_id')
                ->leftJoin('professions as p', 'p.id', '=', 'ms.profession_id')
                ->where('ms.assessment_period_id', $periodId)
                ->get([
                    'u.id as user_id',
                    'u.name as user_name',
                    'ms.unit_id as unit_id',
                    'un.name as unit_name',
                    'ms.profession_id as profession_id',
                    'p.name as profession_name',
                ]);
        } else {
            $assessees = DB::table('users as u')
                ->join('role_user as ru', 'ru.user_id', '=', 'u.id')
                ->join('roles as r', 'r.id', '=', 'ru.role_id')
                ->leftJoin('units as un', 'un.id', '=', 'u.unit_id')
                ->leftJoin('professions as p', 'p.id', '=', 'u.profession_id')
                ->where('r.slug', User::ROLE_PEGAWAI_MEDIS)
                ->get([
                    'u.id as user_id',
                    'u.name as user_name',
                    'u.unit_id as unit_id',
                    'un.name as unit_name',
                    'u.profession_id as profession_id',
                    'p.name as profession_name',
                ]);
        }

        if ($assessees->isEmpty()) {
            return [
                'is_complete' => false,
                'missing_count' => 0,
                'missing_count_total' => 0,
                'missing_details' => [],
                'missing_by_assessee' => [],
                'message' => 'Belum ada pegawai yang wajib dinilai pada periode ini.',
            ];
        }

        $criteriaRows = DB::table('performance_criterias')
            ->where('is_360', true)
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name']);

        if ($criteriaRows->isEmpty()) {
            return [
                'is_complete' => false,
                'missing_count' => 0,
                'missing_count_total' => 0,
                'missing_details' => [],
                'missing_by_assessee' => [],
                'message' => 'Belum ada kriteria 360 aktif.',
            ];
        }

        $criteriaIds = $criteriaRows->pluck('id')->map(fn($id) => (int) $id)->values();
        $criteriaNameMap = $criteriaRows->pluck('name', 'id')->mapWithKeys(fn($name, $id) => [(int) $id => (string) $name])->all();

        $availableProfessionIdsByUnit = [];
        foreach ($assessees as $row) {
            $unitId = (int) ($row->unit_id ?? 0);
            $professionId = (int) ($row->profession_id ?? 0);
            if ($unitId > 0 && $professionId > 0) {
                $availableProfessionIdsByUnit[$unitId][$professionId] = true;
            }
        }

        $ruleRows = DB::table('criteria_rater_rules')
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->get(['performance_criteria_id', 'assessor_type']);
        $hasAnyRules = $ruleRows->isNotEmpty();
        $rulesByCriteria = [];
        foreach ($ruleRows as $row) {
            $cid = (int) $row->performance_criteria_id;
            $rulesByCriteria[$cid] = $rulesByCriteria[$cid] ?? [];
            $rulesByCriteria[$cid][] = (string) $row->assessor_type;
        }

        $assesseeProfessionIds = $assessees->pluck('profession_id')->filter()->map(fn($id) => (int) $id)->unique()->values();
        $prlRows = DB::table('profession_reporting_lines')
            ->whereIn('assessee_profession_id', $assesseeProfessionIds)
            ->where('is_active', true)
            ->where('is_required', true)
            ->get([
                'assessee_profession_id',
                'assessor_profession_id',
                'relation_type',
                'level',
            ]);

        $prlMap = [];
        $prlSupervisorMap = [];
        foreach ($prlRows as $row) {
            $assesseeProfessionId = (int) $row->assessee_profession_id;
            $assessorProfessionId = (int) $row->assessor_profession_id;
            $relation = (string) $row->relation_type;
            $level = $row->level === null ? 0 : (int) $row->level;
            $prlMap[$assesseeProfessionId][$relation][] = [
                'assessor_profession_id' => $assessorProfessionId,
                'level' => $level,
            ];
            if ($relation === 'supervisor') {
                $prlSupervisorMap[$assesseeProfessionId][$assessorProfessionId][] = $level;
            }
        }

        $professionRows = DB::table('professions')->get(['id', 'name']);
        $professionNameMap = $professionRows->pluck('name', 'id')->mapWithKeys(fn($name, $id) => [(int) $id => (string) $name])->all();

        $headUsers = DB::table('users as u')
            ->join('role_user as ru', 'ru.user_id', '=', 'u.id')
            ->join('roles as r', 'r.id', '=', 'ru.role_id')
            ->whereIn('r.slug', [User::ROLE_KEPALA_UNIT, User::ROLE_KEPALA_POLIKLINIK])
            ->get(['u.id as user_id', 'u.name as user_name', 'u.unit_id', 'u.profession_id', 'r.slug as role_slug']);

        $headsByUnit = [];
        $polyclinicHeads = [];
        foreach ($headUsers as $head) {
            $role = (string) $head->role_slug;
            $payload = [
                'user_id' => (int) $head->user_id,
                'user_name' => (string) $head->user_name,
                'profession_id' => $head->profession_id ? (int) $head->profession_id : null,
            ];

            if ($role === User::ROLE_KEPALA_UNIT) {
                $unitId = (int) $head->unit_id;
                $headsByUnit[$unitId][] = $payload;
            }

            if ($role === User::ROLE_KEPALA_POLIKLINIK) {
                $polyclinicHeads[] = $payload;
            }
        }

        $assessmentRows = DB::table('multi_rater_assessments as mra')
            ->where('mra.assessment_period_id', $periodId)
            ->where('mra.status', 'submitted')
            ->get([
                'mra.id',
                'mra.assessee_id',
                'mra.assessor_id',
                'mra.assessor_type',
                'mra.assessor_profession_id',
                'mra.assessor_level',
            ]);

        $assessorIds = $assessmentRows->pluck('assessor_id')->filter()->map(fn($id) => (int) $id)->unique()->values();
        $assessorNameMap = [];
        if ($assessorIds->isNotEmpty()) {
            $assessorNameMap = DB::table('users')
                ->whereIn('id', $assessorIds)
                ->pluck('name', 'id')
                ->mapWithKeys(fn($name, $id) => [(int) $id => (string) $name])
                ->all();
        }

        $assessmentExists = [];
        $assessmentExistsByAssessor = [];
        $assessorsByKey = [];
        foreach ($assessmentRows as $row) {
            $assesseeId = (int) $row->assessee_id;
            $assessorId = (int) ($row->assessor_id ?? 0);
            $type = (string) $row->assessor_type;
            $profId = (int) ($row->assessor_profession_id ?? 0);
            $level = (int) ($row->assessor_level ?? 0);
            $key = $assesseeId . '|' . $type . '|' . $profId . '|' . $level;
            $assessmentExists[$key] = true;
            if ($assessorId > 0) {
                $assessmentExistsByAssessor[$key . '|' . $assessorId] = true;
                $assessorsByKey[$key][$assessorId] = $assessorNameMap[$assessorId] ?? '-';
            }
        }

        $detailRows = DB::table('multi_rater_assessment_details as d')
            ->join('multi_rater_assessments as mra', 'mra.id', '=', 'd.multi_rater_assessment_id')
            ->where('mra.assessment_period_id', $periodId)
            ->where('mra.status', 'submitted')
            ->get([
                'mra.assessee_id',
                'mra.assessor_id',
                'mra.assessor_type',
                'mra.assessor_profession_id',
                'mra.assessor_level',
                'd.performance_criteria_id',
                'd.score',
            ]);

        $scoredByKey = [];
        $scoredByAssessor = [];
        foreach ($detailRows as $row) {
            if ($row->score === null) {
                continue;
            }
            $assesseeId = (int) $row->assessee_id;
            $assessorId = (int) ($row->assessor_id ?? 0);
            $type = (string) $row->assessor_type;
            $profId = (int) ($row->assessor_profession_id ?? 0);
            $level = (int) ($row->assessor_level ?? 0);
            $criteriaId = (int) $row->performance_criteria_id;
            $key = $assesseeId . '|' . $type . '|' . $profId . '|' . $level;
            $scoredByKey[$key][$criteriaId] = true;
            if ($assessorId > 0) {
                $scoredByAssessor[$key . '|' . $assessorId][$criteriaId] = true;
            }
        }

        $relationLabels = [
            'self' => 'Diri sendiri',
            'supervisor' => 'Atasan',
            'peer' => 'Rekan',
            'subordinate' => 'Bawahan',
        ];

        $missingDetails = [];
        $missingByAssessee = [];
        $requiredCountByAssessee = [];
        $missingCountByAssessee = [];
        $assessorProgressByAssessee = [];

        $addMissing = function (array $payload) use (&$missingDetails, &$missingByAssessee, &$missingCountByAssessee) {
            $missingDetails[] = $payload;
            $key = (int) ($payload['assessee_user_id'] ?? 0);
            if (!isset($missingByAssessee[$key])) {
                $missingByAssessee[$key] = [
                    'assessee_id' => (int) ($payload['assessee_user_id'] ?? 0),
                    'assessee_name' => (string) ($payload['assessee_name'] ?? '-'),
                    'items' => [],
                ];
            }
            $missingByAssessee[$key]['items'][] = [
                'assessor_label' => (string) ($payload['required_assessor_relation_label'] ?? '-'),
                'assessor_name' => (string) ($payload['required_assessor_name'] ?? '-'),
                'criteria_name' => (string) ($payload['criteria_name'] ?? '-'),
            ];
            $missingCountByAssessee[$key] = (int) ($missingCountByAssessee[$key] ?? 0) + 1;
        };

        foreach ($assessees as $assessee) {
            $assesseeId = (int) $assessee->user_id;
            $assesseeName = (string) ($assessee->user_name ?? '-');
            $unitId = (int) ($assessee->unit_id ?? 0);
            $unitName = (string) ($assessee->unit_name ?? '-');
            $assesseeProfessionId = (int) ($assessee->profession_id ?? 0);
            $assesseeProfessionName = (string) ($assessee->profession_name ?? '-');

            foreach ($criteriaRows as $criteria) {
                $criteriaId = (int) $criteria->id;
                $criteriaName = (string) ($criteria->name ?? ('Kriteria #' . $criteriaId));

                $requiredTypes = $rulesByCriteria[$criteriaId] ?? ($hasAnyRules ? [] : ['self', 'supervisor', 'peer', 'subordinate']);
                $progressItems = [];

                if (empty($requiredTypes)) {
                    $addMissing([
                        'assessee_user_id' => $assesseeId,
                        'assessee_name' => $assesseeName,
                        'assessee_unit_name' => $unitName,
                        'assessee_profession_name' => $assesseeProfessionName,
                        'criteria_id' => $criteriaId,
                        'criteria_name' => $criteriaName,
                        'required_assessor_type' => 'unknown',
                        'required_assessor_relation_label' => 'Aturan penilai belum diatur',
                        'required_assessor_level' => null,
                        'required_assessor_profession_name' => '-',
                        'required_assessor_name' => '-',
                        'reason' => 'invalid_configuration',
                    ]);
                } else {
                    foreach ($requiredTypes as $type) {
                        $type = (string) $type;
                        $relationLabel = $relationLabels[$type] ?? ucfirst($type ?: 'Penilai');

                        if ($type === 'self') {
                            if ($assesseeProfessionId <= 0) {
                                $addMissing([
                                    'assessee_user_id' => $assesseeId,
                                    'assessee_name' => $assesseeName,
                                    'assessee_unit_name' => $unitName,
                                    'assessee_profession_name' => $assesseeProfessionName,
                                    'criteria_id' => $criteriaId,
                                    'criteria_name' => $criteriaName,
                                    'required_assessor_type' => $type,
                                    'required_assessor_relation_label' => $relationLabel,
                                    'required_assessor_level' => null,
                                    'required_assessor_profession_name' => '-',
                                    'required_assessor_name' => $assesseeName,
                                    'reason' => 'invalid_configuration',
                                ]);
                                continue;
                            }

                            $requiredCountByAssessee[$assesseeId] = (int) ($requiredCountByAssessee[$assesseeId] ?? 0) + 1;
                            $key = $assesseeId . '|' . $type . '|' . $assesseeProfessionId . '|0';
                            $hasAssessment = !empty($assessmentExists[$key]);
                            $hasScore = !empty($scoredByKey[$key][$criteriaId]);

                            $progressItems[] = [
                                'relation_label' => $relationLabel,
                                'assessor_name' => $assesseeName,
                                'assessor_role_label' => null,
                                'profession_name' => $professionNameMap[$assesseeProfessionId] ?? $assesseeProfessionName,
                                'level' => null,
                                'status' => $hasScore ? 'filled' : 'missing',
                                'status_label' => $hasScore ? 'Sudah mengisi' : 'Belum mengisi',
                                'reason' => null,
                            ];

                            if (!$hasAssessment) {
                                $addMissing([
                                    'assessee_user_id' => $assesseeId,
                                    'assessee_name' => $assesseeName,
                                    'assessee_unit_name' => $unitName,
                                    'assessee_profession_name' => $assesseeProfessionName,
                                    'criteria_id' => $criteriaId,
                                    'criteria_name' => $criteriaName,
                                    'required_assessor_type' => $type,
                                    'required_assessor_relation_label' => $relationLabel,
                                    'required_assessor_level' => null,
                                    'required_assessor_profession_name' => $professionNameMap[$assesseeProfessionId] ?? $assesseeProfessionName,
                                    'required_assessor_name' => $assesseeName,
                                    'reason' => 'missing_assessment',
                                ]);
                            } elseif (!$hasScore) {
                                $addMissing([
                                    'assessee_user_id' => $assesseeId,
                                    'assessee_name' => $assesseeName,
                                    'assessee_unit_name' => $unitName,
                                    'assessee_profession_name' => $assesseeProfessionName,
                                    'criteria_id' => $criteriaId,
                                    'criteria_name' => $criteriaName,
                                    'required_assessor_type' => $type,
                                    'required_assessor_relation_label' => $relationLabel,
                                    'required_assessor_level' => null,
                                    'required_assessor_profession_name' => $professionNameMap[$assesseeProfessionId] ?? $assesseeProfessionName,
                                    'required_assessor_name' => $assesseeName,
                                    'reason' => 'missing_score',
                                ]);
                            }

                            continue;
                        }

                        $requiredLines = $prlMap[$assesseeProfessionId][$type] ?? [];
                        if (empty($requiredLines)) {
                            $addMissing([
                                'assessee_user_id' => $assesseeId,
                                'assessee_name' => $assesseeName,
                                'assessee_unit_name' => $unitName,
                                'assessee_profession_name' => $assesseeProfessionName,
                                'criteria_id' => $criteriaId,
                                'criteria_name' => $criteriaName,
                                'required_assessor_type' => $type,
                                'required_assessor_relation_label' => $relationLabel,
                                'required_assessor_level' => null,
                                'required_assessor_profession_name' => '-',
                                'required_assessor_name' => '-',
                                'reason' => 'invalid_configuration',
                            ]);
                            continue;
                        }

                        foreach ($requiredLines as $line) {
                            $requiredProfId = (int) ($line['assessor_profession_id'] ?? 0);
                            $requiredLevel = (int) ($line['level'] ?? 0);
                            $isAvailable = $unitId > 0 && !empty($availableProfessionIdsByUnit[$unitId][$requiredProfId]);
                            if (!$isAvailable) {
                                continue;
                            }

                            $requiredCountByAssessee[$assesseeId] = (int) ($requiredCountByAssessee[$assesseeId] ?? 0) + 1;

                            $key = $assesseeId . '|' . $type . '|' . $requiredProfId . '|' . $requiredLevel;
                            $hasAssessment = !empty($assessmentExists[$key]);
                            $hasScore = !empty($scoredByKey[$key][$criteriaId]);

                            $professionName = $professionNameMap[$requiredProfId] ?? '-';
                            if ($type === 'supervisor') {
                                $unitHeads = array_values(array_filter($headsByUnit[$unitId] ?? [], fn($h) => (int) ($h['profession_id'] ?? 0) === (int) $requiredProfId));
                                $poliHeads = array_values(array_filter($polyclinicHeads ?? [], fn($h) => (int) ($h['profession_id'] ?? 0) === (int) $requiredProfId));
                                $assessorsList = $assessorsByKey[$key] ?? [];

                                if (!empty($unitHeads)) {
                                    foreach ($unitHeads as $head) {
                                        $assessorId = (int) ($head['user_id'] ?? 0);
                                        $isFilled = !empty($scoredByAssessor[$key . '|' . $assessorId][$criteriaId]);
                                        $progressItems[] = [
                                            'relation_label' => $relationLabel,
                                            'assessor_name' => (string) ($head['user_name'] ?? '-'),
                                            'assessor_role_label' => 'Kepala Unit',
                                            'profession_name' => $professionName,
                                            'level' => $requiredLevel > 0 ? $requiredLevel : null,
                                            'status' => $isFilled ? 'filled' : 'missing',
                                            'status_label' => $isFilled ? 'Sudah mengisi' : 'Belum mengisi',
                                            'reason' => null,
                                        ];
                                    }
                                }

                                if (!empty($poliHeads)) {
                                    foreach ($poliHeads as $head) {
                                        $assessorId = (int) ($head['user_id'] ?? 0);
                                        $isFilled = !empty($scoredByAssessor[$key . '|' . $assessorId][$criteriaId]);
                                        $progressItems[] = [
                                            'relation_label' => $relationLabel,
                                            'assessor_name' => (string) ($head['user_name'] ?? '-'),
                                            'assessor_role_label' => 'Kepala Poliklinik',
                                            'profession_name' => $professionName,
                                            'level' => $requiredLevel > 0 ? $requiredLevel : null,
                                            'status' => $isFilled ? 'filled' : 'missing',
                                            'status_label' => $isFilled ? 'Sudah mengisi' : 'Belum mengisi',
                                            'reason' => null,
                                        ];
                                    }
                                }

                                if (empty($unitHeads) && empty($poliHeads) && !empty($assessorsList)) {
                                    foreach ($assessorsList as $assessorId => $assessorName) {
                                        $isFilled = !empty($scoredByAssessor[$key . '|' . $assessorId][$criteriaId]);
                                        $progressItems[] = [
                                            'relation_label' => $relationLabel,
                                            'assessor_name' => (string) $assessorName,
                                            'assessor_role_label' => null,
                                            'profession_name' => $professionName,
                                            'level' => $requiredLevel > 0 ? $requiredLevel : null,
                                            'status' => $isFilled ? 'filled' : 'missing',
                                            'status_label' => $isFilled ? 'Sudah mengisi' : 'Belum mengisi',
                                            'reason' => null,
                                        ];
                                    }
                                }

                                if (empty($unitHeads) && empty($poliHeads) && empty($assessorsList)) {
                                    $progressItems[] = [
                                        'relation_label' => $relationLabel,
                                        'assessor_name' => 'Minimal 1 penilai profesi ' . $professionName,
                                        'assessor_role_label' => null,
                                        'profession_name' => $professionName,
                                        'level' => $requiredLevel > 0 ? $requiredLevel : null,
                                        'status' => $hasScore ? 'filled' : 'missing',
                                        'status_label' => $hasScore ? 'Sudah mengisi' : 'Belum mengisi',
                                        'reason' => null,
                                    ];
                                }
                            } else {
                                $assessorsList = $assessorsByKey[$key] ?? [];
                                if (!empty($assessorsList)) {
                                    foreach ($assessorsList as $assessorId => $assessorName) {
                                        $isFilled = !empty($scoredByAssessor[$key . '|' . $assessorId][$criteriaId]);
                                        $progressItems[] = [
                                            'relation_label' => $relationLabel,
                                            'assessor_name' => (string) $assessorName,
                                            'assessor_role_label' => null,
                                            'profession_name' => $professionName,
                                            'level' => $requiredLevel > 0 ? $requiredLevel : null,
                                            'status' => $isFilled ? 'filled' : 'missing',
                                            'status_label' => $isFilled ? 'Sudah mengisi' : 'Belum mengisi',
                                            'reason' => null,
                                        ];
                                    }
                                } else {
                                    $progressItems[] = [
                                        'relation_label' => $relationLabel,
                                        'assessor_name' => 'Minimal 1 penilai profesi ' . $professionName,
                                        'assessor_role_label' => null,
                                        'profession_name' => $professionName,
                                        'level' => $requiredLevel > 0 ? $requiredLevel : null,
                                        'status' => $hasScore ? 'filled' : 'missing',
                                        'status_label' => $hasScore ? 'Sudah mengisi' : 'Belum mengisi',
                                        'reason' => null,
                                    ];
                                }
                            }

                            if (!$hasAssessment) {
                                $addMissing([
                                    'assessee_user_id' => $assesseeId,
                                    'assessee_name' => $assesseeName,
                                    'assessee_unit_name' => $unitName,
                                    'assessee_profession_name' => $assesseeProfessionName,
                                    'criteria_id' => $criteriaId,
                                    'criteria_name' => $criteriaName,
                                    'required_assessor_type' => $type,
                                    'required_assessor_relation_label' => $relationLabel,
                                    'required_assessor_level' => $requiredLevel > 0 ? $requiredLevel : null,
                                    'required_assessor_profession_name' => $professionNameMap[$requiredProfId] ?? '-',
                                    'required_assessor_name' => 'Belum ada penilai',
                                    'reason' => 'missing_assessment',
                                ]);
                            } elseif (!$hasScore) {
                                $addMissing([
                                    'assessee_user_id' => $assesseeId,
                                    'assessee_name' => $assesseeName,
                                    'assessee_unit_name' => $unitName,
                                    'assessee_profession_name' => $assesseeProfessionName,
                                    'criteria_id' => $criteriaId,
                                    'criteria_name' => $criteriaName,
                                    'required_assessor_type' => $type,
                                    'required_assessor_relation_label' => $relationLabel,
                                    'required_assessor_level' => $requiredLevel > 0 ? $requiredLevel : null,
                                    'required_assessor_profession_name' => $professionNameMap[$requiredProfId] ?? '-',
                                    'required_assessor_name' => 'Belum ada penilai',
                                    'reason' => 'missing_score',
                                ]);
                            }
                        }
                    }
                }

                $assessorProgressByAssessee[$assesseeId][] = [
                    'criteria_id' => $criteriaId,
                    'criteria_name' => $criteriaName,
                    'assessors' => $progressItems,
                ];
            }
        }

        $missingCount = count($missingDetails);

        $assesseeSummary = [];
        $completeCountTotal = 0;
        $incompleteCountTotal = 0;
        foreach ($assessees as $assessee) {
            $assesseeId = (int) $assessee->user_id;
            $totalRequired = (int) ($requiredCountByAssessee[$assesseeId] ?? 0);
            $missingCountBy = (int) ($missingCountByAssessee[$assesseeId] ?? 0);
            $completedCount = max(0, $totalRequired - $missingCountBy);
            $status = $missingCountBy > 0 ? 'incomplete' : 'complete';
            if ($status === 'complete') {
                $completeCountTotal++;
            } else {
                $incompleteCountTotal++;
            }

            $assesseeSummary[] = [
                'assessee_id' => $assesseeId,
                'assessee_name' => (string) ($assessee->user_name ?? '-'),
                'unit_name' => (string) ($assessee->unit_name ?? '-'),
                'profession_name' => (string) ($assessee->profession_name ?? '-'),
                'status' => $status,
                'missing_count' => $missingCountBy,
                'completed_count' => $completedCount,
                'total_required_count' => $totalRequired,
                'assessor_progress' => $assessorProgressByAssessee[$assesseeId] ?? [],
            ];
        }

        return [
            'is_complete' => $missingCount === 0,
            'missing_count' => $missingCount,
            'missing_count_total' => $missingCount,
            'missing_details' => $missingDetails,
            'missing_by_assessee' => array_values($missingByAssessee),
            'assessee_summary' => $assesseeSummary,
            'complete_count_total' => $completeCountTotal,
            'incomplete_count_total' => $incompleteCountTotal,
            'message' => $missingCount === 0 ? 'Penilaian 360 sudah lengkap.' : 'Masih ada Penilaian 360 yang belum lengkap.',
        ];
    }

}
