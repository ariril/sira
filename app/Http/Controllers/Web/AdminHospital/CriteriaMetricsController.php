<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\CriteriaMetric;
use App\Models\PerformanceCriteria;
use App\Models\AssessmentPeriod;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CriteriaMetricsController extends Controller
{
    public function index(Request $request): View
    {
        $periodId = (int)$request->query('period_id');
        $items = CriteriaMetric::query()
            ->when($periodId, fn($w) => $w->where('assessment_period_id', $periodId))
            ->with(['user:id,name,employee_number','criteria:id,name','period:id,name'])
            ->orderByDesc('id')->paginate(15)->withQueryString();

        $periods = AssessmentPeriod::orderByDesc('start_date')->pluck('name','id');
        $criterias = PerformanceCriteria::where('is_active', true)->orderBy('name')->get(['id','name','data_type']);
        $criteriaOptions = $criterias->pluck('name','id');
        return view('admin_rs.metrics.index', compact('items','periods','criterias','criteriaOptions','periodId'));
    }

    public function create(): View
    {
        abort(404);
    }

    public function store(Request $request): RedirectResponse
    {
        abort(404);
    }

    public function uploadCsv(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt,xls,xlsx|max:5120',
            'performance_criteria_id' => 'required|exists:performance_criterias,id',
            'replace_existing' => 'nullable|boolean',
        ]);

        $criteria = PerformanceCriteria::findOrFail($validated['performance_criteria_id']);

        // Periode aktif wajib ada
        $active = AssessmentPeriod::query()->active()->orderByDesc('start_date')->first();
        if (!$active) {
            return back()->withErrors(['file' => 'Tidak ada Periode Aktif. Aktifkan periode lebih dulu.']);
        }

        $path = $request->file('file')->store('metric_uploads');

        // Helper pembaca tabular (CSV/XLS/XLSX)
        $readTabular = function (string $absPath, string $ext): array {
            $ext = strtolower($ext);
            if (in_array($ext, ['csv','txt'])) {
                $fh = fopen($absPath, 'r');
                if ($fh === false) throw new \RuntimeException('Gagal membuka file');
                $header = fgetcsv($fh);
                $rows = [];
                while (($r = fgetcsv($fh)) !== false) { $rows[] = $r; }
                fclose($fh);
                return [$header, $rows, 'csv'];
            }
            // Excel via PhpSpreadsheet
            try {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($absPath);
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($absPath);
                $sheet = $spreadsheet->getSheet(0);
                $rows = $sheet->toArray(null, true, true, false);
                $header = array_shift($rows);
                return [$header, $rows, 'excel'];
            } catch (\Throwable $e) {
                throw new \RuntimeException('Gagal membaca Excel: '.$e->getMessage());
            }
        };

        // Normalisasi header ke indeks kolom
        $mapHeader = function (array $header): array {
            $norm = array_map(function($h){
                $h = strtolower((string)$h);
                return trim(preg_replace('/\s+/', ' ', $h));
            }, $header);
            $find = function(array $aliases) use ($norm) {
                foreach ($aliases as $a) {
                    $i = array_search($a, $norm, true);
                    if ($i !== false) return $i;
                }
                return false;
            };
            return [
                'employee_number'        => $find(['employee_number','nip','pin','nip/pin']),
                'criteria_id'            => $find(['performance_criteria_id','id_kriteria','kriteria_id']),
                'criteria_name'          => $find(['kriteria','nama_kriteria']),
                // nilai sesuai tipe data
                'value_numeric'          => $find(['value_numeric','nilai','nilai_angka','value']),
                'value_datetime'         => $find(['value_datetime','nilai_tanggal','tanggal_nilai','tanggal']),
                'value_text'             => $find(['value_text','nilai_teks','keterangan']),
            ];
        };

        $dataType = (string)($criteria->data_type ?? 'numeric');

        try {
            [$header, $rows, $src] = $readTabular(Storage::path($path), $request->file('file')->getClientOriginalExtension());
            if (!$header) return back()->withErrors(['file' => 'File kosong atau header tidak ditemukan.']);
            $map = $mapHeader($header);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Import gagal: '.$e->getMessage()]);
        }

        $requiredKey = match ($dataType) {
            'datetime' => 'value_datetime',
            'text'     => 'value_text',
            default    => 'value_numeric',
        };

        if ($map[$requiredKey] === false) {
            return back()->withErrors(['file' => 'Header tidak sesuai template untuk kriteria ini. Unduh ulang template dan coba lagi.']);
        }

        $created = 0; $updated = 0; $skipped = 0;
        $overwrite = (bool)($validated['replace_existing'] ?? false);

        foreach ($rows as $row) {
            // Ambil NIP -> user
            $emp = $map['employee_number'] !== false ? trim((string)($row[$map['employee_number']] ?? '')) : '';
            if ($emp === '') { $skipped++; continue; }
            $user = User::where('employee_number', $emp)->first();
            if (!$user) { $skipped++; continue; }

            // Tentukan kolom nilai berdasarkan data_type
            $valueNum = null; $valueDt = null; $valueTxt = null;
            $getVal = function($key) use ($map, $row) {
                if ($map[$key] === false) return null;
                return $row[$map[$key]] ?? null;
            };

            if (in_array($dataType, ['numeric','percentage','boolean'])) {
                $raw = (string)$getVal('value_numeric');
                if ($dataType === 'percentage') { $raw = str_replace('%','',$raw); }
                if ($dataType === 'boolean') {
                    $v = strtolower(trim($raw));
                    $raw = ($v === '1' || $v === 'true' || $v === 'ya' || $v === 'y' || $v === 'yes') ? '1' : (($v === '0' || $v === 'false' || $v === 'tidak' || $v === 'no') ? '0' : $raw);
                }
                $raw = str_replace([','], ['.'], $raw);
                $valueNum = is_numeric($raw) ? (float)$raw : null;
            } elseif ($dataType === 'datetime') {
                $raw = (string)$getVal('value_datetime');
                $valueDt = $raw ? (date('Y-m-d H:i:s', strtotime($raw)) ?: null) : null;
            } else { // text
                $valueTxt = (string)$getVal('value_text');
            }

            // Apakah sudah ada?
            $existing = CriteriaMetric::where('user_id',$user->id)
                ->where('assessment_period_id', $active->id)
                ->where('performance_criteria_id', $criteria->id)
                ->first();

            if ($existing) {
                if (!$overwrite) { $skipped++; continue; }
                $existing->update([
                    'value_numeric' => $valueNum,
                    'value_datetime'=> $valueDt,
                    'value_text'    => $valueTxt,
                    'source_type'   => 'import',
                    'source_table'  => $src,
                ]);
                $updated++;
                continue;
            }

            CriteriaMetric::create([
                'user_id' => $user->id,
                'assessment_period_id' => $active->id,
                'performance_criteria_id' => $criteria->id,
                'value_numeric' => $valueNum,
                'value_datetime'=> $valueDt,
                'value_text'    => $valueTxt,
                'source_type'   => 'import',
                'source_table'  => $src,
            ]);
            $created++;
        }

        $msg = "Import selesai: dibuat {$created}, diperbarui {$updated}, dilewati {$skipped}.";
        return back()->with('status', $msg);
    }

    public function downloadTemplate(Request $request)
    {
        $data = $request->validate([
            'performance_criteria_id' => 'required|exists:performance_criterias,id',
            'period_id' => 'nullable|exists:assessment_periods,id',
        ]);

        $criteria = PerformanceCriteria::findOrFail($data['performance_criteria_id']);
        $period = isset($data['period_id'])
            ? AssessmentPeriod::find($data['period_id'])
            : AssessmentPeriod::query()->active()->orderByDesc('start_date')->first();

        if (!$period) {
            return back()->withErrors(['performance_criteria_id' => 'Periode aktif tidak ditemukan. Aktifkan periode terlebih dahulu.']);
        }

        $valueColumn = match ($criteria->data_type ?? 'numeric') {
            'datetime'   => 'nilai_tanggal',
            'text'       => 'nilai_teks',
            default      => 'nilai',
        };

        $sheet = new Spreadsheet();
        $sheet->getProperties()
            ->setCreator('SIRA')
            ->setTitle('Template Metrics '.$criteria->name);
        $ws = $sheet->getActiveSheet();
        $ws->setTitle('Template');

        $headers = ['nip','nama','periode','id_kriteria','kriteria','tipe_data', $valueColumn];
        foreach ($headers as $idx => $head) {
            $col = Coordinate::stringFromColumnIndex($idx + 1);
            $ws->setCellValue($col.'1', $head);
        }

        $users = User::orderBy('name')->get(['name','employee_number']);
        $row = 2;
        foreach ($users as $user) {
            $ws->setCellValue('A'.$row, $user->employee_number);
            $ws->setCellValue('B'.$row, $user->name);
            $ws->setCellValue('C'.$row, $period->name);
            $ws->setCellValue('D'.$row, $criteria->id);
            $ws->setCellValue('E'.$row, $criteria->name);
            $ws->setCellValue('F'.$row, $criteria->data_type);
            $ws->setCellValue('G'.$row, '');
            $row++;
        }

        // hint baris pertama data jika tidak ada user
        if ($row === 2) {
            $ws->setCellValue('A2', 'ISI_NIP');
            $ws->setCellValue('B2', 'Nama Pegawai');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'metric_tpl_');
        $writer = new Xlsx($sheet);
        $writer->save($tmp);

        $filename = 'template-metrics-'.$criteria->id.'-'.$period->id.'.xlsx';
        return response()->download($tmp, $filename)->deleteFileAfterSend(true);
    }
}
