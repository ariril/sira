<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class UserImportController extends Controller
{
    public function form(): View
    {
        return view('super_admin.users.import');
    }

    public function template()
    {
        $headers = ['name','email','roles','profession_id','employee_number','unit_id','password'];
        $samples = [
            ['Admin Baru','admin_baru@example.com','super_admin','','ADM001','','password'],
            ['Perawat A','perawat.a@example.com','pegawai_medis','3','PRW123','12','password'],
            ['Kepala Unit B','kepala.unitb@example.com','kepala_unit','5','KUB555','7','password'],
        ];

        $callback = function() use ($headers,$samples) {
            $fh = fopen('php://output','w');
            fputcsv($fh,$headers);
            foreach($samples as $row) { fputcsv($fh,$row); }
            fclose($fh);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_import_pengguna.csv"'
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
        $rows = [];
        $header = null;
        if (in_array($ext, ['csv','txt'])) {
            $handle = fopen($path, 'r');
            if (!$handle) {
                $error = 'Tidak dapat membaca file.';
                return view('super_admin.users.import', compact('error'));
            }
            while (($line = fgetcsv($handle, 2000, ',')) !== false) {
                if ($header === null) { $header = $line; continue; }
                if (count(array_filter($line)) === 0) { continue; }
                $rows[] = array_combine($header, $line);
            }
            fclose($handle);
        } else { // excel
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
                $sheet = $spreadsheet->getSheet(0);
                $highestRow = $sheet->getHighestDataRow();
                $highestColumn = $sheet->getHighestDataColumn();
                $header = [];
                foreach ($sheet->rangeToArray('A1:'.$highestColumn.'1', null, true, true, true)[1] as $cellVal) {
                    $header[] = trim($cellVal);
                }
                for ($row = 2; $row <= $highestRow; $row++) {
                    $rowVals = $sheet->rangeToArray('A'.$row.':'.$highestColumn.$row, null, true, true, true)[$row];
                    if (count(array_filter($rowVals)) === 0) { continue; }
                    $assoc = [];
                    $i = 0;
                    foreach ($rowVals as $cellVal) {
                        $key = $header[$i] ?? 'col_'.$i;
                        $assoc[$key] = trim((string)$cellVal);
                        $i++;
                    }
                    $rows[] = $assoc;
                }
            } catch (\Throwable $e) {
                $error = 'Gagal membaca Excel: '.$e->getMessage();
                return view('super_admin.users.import', compact('error'));
            }
        }

        $results = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $r) {
                $email = trim($r['email'] ?? '');
                $name  = trim($r['name'] ?? '');
                $rolesCsv = trim($r['roles'] ?? '');
                $professionId = $r['profession_id'] ?? null; // optional numeric ID mapping
                $employeeNumber = trim($r['employee_number'] ?? '');
                $unitId = $r['unit_id'] ?? null;
                $plainPassword = trim($r['password'] ?? '');

                if ($email === '' || $name === '') {
                    $results[] = ['row' => $i+2, 'email' => $email, 'status' => 'skip', 'reason' => 'Nama atau email kosong', 'raw' => $r];
                    continue;
                }
                if (User::where('email', $email)->exists()) {
                    $results[] = ['row' => $i+2, 'email' => $email, 'status' => 'skip', 'reason' => 'Email sudah ada', 'raw' => $r];
                    continue;
                }

                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($plainPassword !== '' ? $plainPassword : Str::random(12)),
                    'profession_id' => $professionId ?: null,
                    'employee_number' => $employeeNumber ?: null,
                    'unit_id' => $unitId ?: null,
                ]);

                $attachRoleIds = [];
                if ($rolesCsv !== '') {
                    $slugs = array_filter(array_map('trim', explode(',', $rolesCsv)));
                    if (!empty($slugs)) {
                        $attachRoleIds = Role::whereIn('slug', $slugs)->pluck('id')->all();
                    }
                }
                if (!empty($attachRoleIds)) {
                    $user->roles()->attach($attachRoleIds);
                    // Set last_role if possible
                    if (!$user->last_role && !empty($attachRoleIds)) {
                        $user->last_role = Role::find($attachRoleIds[0])?->slug;
                        $user->save();
                    }
                }
                $reason = empty($attachRoleIds) ? 'Ditambahkan (tanpa peran)' : 'Ditambahkan';
                $results[] = ['row' => $i+2, 'email' => $email, 'status' => 'ok', 'reason' => $reason, 'raw' => $r];
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $error = 'Gagal impor: '.$e->getMessage();
            return view('super_admin.users.import', compact('error'));
        }

        return view('super_admin.users.import_result', [
            'results' => $results,
            'count_ok' => collect($results)->where('status','ok')->count(),
            'count_skip' => collect($results)->where('status','skip')->count(),
        ]);
    }
}
