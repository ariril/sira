<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Profession;
use App\Models\Unit;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Services\Users\Imports\UserImportFileParser;

class UserImportController extends Controller
{
    private const TEMPLATE_HEADERS = [
        'name',
        'email',
        'roles',
        'profession_slug',
        'employee_number',
        'unit_slug',
        'password',
        'start_date',
        'gender',
        'nationality',
        'address',
        'phone',
        'last_education',
        'position',
    ];

    public function __construct(
        private readonly UserImportFileParser $userImportFileParser,
    ) {
    }

    public function form(): View
    {
        return view('super_admin.users.import');
    }

    public function template()
    {
        $headers = self::TEMPLATE_HEADERS;

        $callback = function() use ($headers) {
            $fh = fopen('php://output','w');
            fputcsv($fh,$headers);
            fclose($fh);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users_import_template.csv"'
        ]);
    }

    public function process(Request $request): View
    {
        $data = $request->validate([
            'file'      => ['required','file','mimes:csv,txt,xlsx,xls'],
        ]);

        $uploaded = $request->file('file');
        $ext = strtolower($uploaded->getClientOriginalExtension());
        $path = $uploaded->getRealPath();

        $parsed = $this->userImportFileParser->parseImportFile($path, $ext);
        if (isset($parsed['error'])) {
            $error = $parsed['error'];
            return view('super_admin.users.import', compact('error'));
        }

        $header = $parsed['header'];
        $rows = $parsed['rows']; // each row: ['row_number' => int, 'data' => array]

        $expectedHeaders = self::TEMPLATE_HEADERS;
        $missingHeaders = array_values(array_diff($expectedHeaders, $header));
        if (!empty($missingHeaders)) {
            $error = 'Header tidak sesuai. Kolom wajib hilang: '.implode(', ', $missingHeaders);
            return view('super_admin.users.import', compact('error'));
        }

        $unitMap = Unit::pluck('id', 'slug')->all();

        // Professions: accept `profession_slug` as one of:
        // - professions.slug (if column exists)
        // - professions.code
        // - slugified professions.name (kebab-case)
        $professionMap = [];
        $professionHasSlug = Schema::hasColumn('professions', 'slug');
        $professionQuery = Profession::query()->select(['id', 'name', 'code']);
        if ($professionHasSlug) {
            $professionQuery->addSelect('slug');
        }
        foreach ($professionQuery->get() as $p) {
            $id = (int) $p->id;

            if ($professionHasSlug) {
                $slug = $this->blankToNull($p->slug);
                if ($slug !== null) {
                    $professionMap[$slug] = $id;
                }
            }

            $code = $this->blankToNull($p->code);
            if ($code !== null) {
                $professionMap[$code] = $id;
                $professionMap[strtolower($code)] = $id;
            }

            $name = $this->blankToNull($p->name);
            if ($name !== null) {
                $nameSlug = Str::slug($name);
                if ($nameSlug !== '') {
                    $professionMap[$nameSlug] = $id;
                }
            }
        }

        $roleMap = Role::pluck('id', 'slug')->all();

        $results = [];
        $errorsByRow = [];
        $successCount = 0;

        foreach ($rows as $rowItem) {
            $rowNumber = $rowItem['row_number'];
            $r = $rowItem['data'];

            $email = $this->blankToNull($r['email'] ?? null);
            $existingUser = $email ? User::where('email', $email)->first() : null;
            $existingUserId = $existingUser?->id;

            $validator = Validator::make($r, [
                'email' => ['required', 'email'],
                'name' => ['nullable'],
                'employee_number' => [
                    'nullable',
                    Rule::unique('users', 'employee_number')->ignore($existingUserId),
                ],
                'start_date' => ['nullable', 'date_format:Y-m-d'],
                'gender' => ['nullable', 'string', 'max:10'],
                'nationality' => ['nullable', 'string', 'max:50'],
                'address' => ['nullable', 'string'],
                'phone' => ['nullable', 'string', 'max:20'],
                'last_education' => ['nullable', 'string', 'max:50'],
                'position' => ['nullable', 'string'],
                'roles' => ['nullable', 'string'],
                'unit_slug' => ['nullable', 'string'],
                'profession_slug' => ['nullable', 'string'],
                'password' => ['nullable', 'string'],
            ], [
                'email.required' => 'email wajib diisi',
                'email.email' => 'format email tidak valid',
                'employee_number.unique' => 'employee_number sudah digunakan',
                'start_date.date_format' => 'start_date harus format YYYY-MM-DD',
                'gender.max' => 'gender maksimal 10 karakter',
                'nationality.max' => 'nationality maksimal 50 karakter',
                'phone.max' => 'phone maksimal 20 karakter',
                'last_education.max' => 'last_education maksimal 50 karakter',
            ]);

            if ($validator->fails()) {
                $msg = implode(' | ', $validator->errors()->all());
                $errorsByRow[] = [
                    'row_number' => $rowNumber,
                    'email' => (string)($email ?? ''),
                    'error_message' => $msg,
                ];
                $results[] = ['row' => $rowNumber, 'email' => (string)($email ?? ''), 'status' => 'failed', 'reason' => $msg, 'raw' => $r];
                continue;
            }

            // Normalize blanks -> null for nullable fields
            $name = $this->blankToNull($r['name'] ?? null);
            $employeeNumber = $this->blankToNull($r['employee_number'] ?? null);
            $startDate = $this->blankToNull($r['start_date'] ?? null);
            $gender = $this->blankToNull($r['gender'] ?? null);
            $nationality = $this->blankToNull($r['nationality'] ?? null);
            $address = $this->blankToNull($r['address'] ?? null);
            $phone = $this->blankToNull($r['phone'] ?? null);
            $lastEducation = $this->blankToNull($r['last_education'] ?? null);
            $position = $this->blankToNull($r['position'] ?? null);
            $plainPassword = $this->blankToNull($r['password'] ?? null);

            $unitSlug = $this->blankToNull($r['unit_slug'] ?? null);
            $professionSlug = $this->blankToNull($r['profession_slug'] ?? null);

            $unitId = null;
            if ($unitSlug !== null) {
                $unitId = $unitMap[$unitSlug] ?? null;
                if ($unitId === null) {
                    $msg = "unit_slug '{$unitSlug}' tidak ditemukan";
                    $errorsByRow[] = ['row_number' => $rowNumber, 'email' => (string)($email ?? ''), 'error_message' => $msg];
                    $results[] = ['row' => $rowNumber, 'email' => (string)($email ?? ''), 'status' => 'failed', 'reason' => $msg, 'raw' => $r];
                    continue;
                }
            }

            $professionId = null;
            if ($professionSlug !== null) {
                $professionId = $professionMap[$professionSlug] ?? null;
                if ($professionId === null) {
                    $msg = "profession_slug '{$professionSlug}' tidak ditemukan";
                    $errorsByRow[] = ['row_number' => $rowNumber, 'email' => (string)($email ?? ''), 'error_message' => $msg];
                    $results[] = ['row' => $rowNumber, 'email' => (string)($email ?? ''), 'status' => 'failed', 'reason' => $msg, 'raw' => $r];
                    continue;
                }
            }

            $rolesCsv = $this->blankToNull($r['roles'] ?? null);
            $roleSlugs = [];
            $roleIds = [];
            if ($rolesCsv !== null) {
                $roleSlugs = array_values(array_unique(array_filter(array_map('trim', explode(',', $rolesCsv)))));
                foreach ($roleSlugs as $slug) {
                    $rid = $roleMap[$slug] ?? null;
                    if ($rid === null) {
                        $msg = "roles: slug '{$slug}' tidak ditemukan";
                        $errorsByRow[] = ['row_number' => $rowNumber, 'email' => (string)($email ?? ''), 'error_message' => $msg];
                        $results[] = ['row' => $rowNumber, 'email' => (string)($email ?? ''), 'status' => 'failed', 'reason' => $msg, 'raw' => $r];
                        continue 2;
                    }
                    $roleIds[] = $rid;
                }
            }

            try {
                $user = $existingUser;
                $isCreate = false;
                if (!$user) {
                    $user = new User();
                    $user->email = (string)$email;
                    $isCreate = true;
                }

                // Assign fields (nullable fields become null, not empty string)
                $user->name = $name;
                $user->employee_number = $employeeNumber;
                $user->start_date = $startDate;
                $user->gender = $gender;
                $user->nationality = $nationality;
                $user->address = $address;
                $user->phone = $phone;
                $user->last_education = $lastEducation;
                $user->position = $position;
                $user->unit_id = $unitId;
                $user->profession_id = $professionId;

                if ($plainPassword !== null) {
                    $user->password = Hash::make($plainPassword);
                } elseif ($isCreate) {
                    $user->password = Hash::make(Str::random(12));
                }

                // last_role: only update when roles provided & non-empty
                if (!empty($roleSlugs)) {
                    $user->last_role = $roleSlugs[0];
                }

                $user->save();

                // Roles: only change when roles column is provided & non-empty
                if (!empty($roleIds)) {
                    $user->roles()->sync($roleIds);
                }

                $successCount++;
                $results[] = [
                    'row' => $rowNumber,
                    'email' => (string)($email ?? ''),
                    'status' => 'ok',
                    'reason' => $existingUser ? 'Diupdate' : 'Dibuat',
                    'raw' => $r,
                ];
            } catch (\Throwable $e) {
                $msg = 'Gagal simpan user: '.$e->getMessage();
                $errorsByRow[] = ['row_number' => $rowNumber, 'email' => (string)($email ?? ''), 'error_message' => $msg];
                $results[] = ['row' => $rowNumber, 'email' => (string)($email ?? ''), 'status' => 'failed', 'reason' => $msg, 'raw' => $r];
                continue;
            }
        }

        $totalRows = count($rows);
        $failedRows = count($errorsByRow);

        return view('super_admin.users.import_result', [
            'total_rows' => $totalRows,
            'success_rows' => $successCount,
            'failed_rows' => $failedRows,
            'row_errors' => $errorsByRow,
            'results' => $results,
        ]);
    }

    private function blankToNull($value): ?string
    {
        if ($value === null) return null;
        $v = trim((string)$value);
        return $v === '' ? null : $v;
    }
}
