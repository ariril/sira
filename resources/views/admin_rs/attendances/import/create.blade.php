<x-app-layout title="Upload Excel Absensi">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Upload Excel Absensi</h1>
            <x-ui.button as="a" href="{{ route('admin_rs.attendances.batches') }}" class="h-12 px-6 text-base">
                <i class="fa-solid fa-database mr-2"></i> Lihat Batch Import
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        @if($errors->any())
        <div class="bg-rose-50 border border-rose-100 text-rose-800 rounded-xl px-4 py-3">
            <ul class="list-disc list-inside text-sm">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <form method="POST" action="{{ route('admin_rs.attendances.import.store') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">File CSV</label>
                    <input type="file" name="file" accept=".csv,text/csv" class="block w-full text-sm" required />
                    <p class="mt-1 text-xs text-slate-500">Ekspor dari Excel sebagai CSV (Comma Separated Values). Maksimal 5 MB.</p>
                </div>
                <div class="flex justify-end">
                    <x-ui.button type="submit" variant="success" class="h-12 px-6 text-base">
                        <i class="fa-solid fa-file-arrow-up mr-2"></i> Unggah & Import
                    </x-ui.button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <h2 class="text-base font-semibold text-slate-800 mb-3">Format CSV</h2>
            <p class="text-sm text-slate-600 mb-4">Gunakan header berikut dalam baris pertama:</p>
            <pre class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-[13px] overflow-auto">employee_number,attendance_date,check_in,check_out,status
01234,2025-10-01,08:00,16:00,Hadir
01234,2025-10-02,08:05,16:10,Terlambat
04567,01/10/2025,07:50,16:00,Hadir</pre>
            <ul class="mt-3 text-sm text-slate-600 list-disc list-inside">
                <li>attendance_date: format YYYY-MM-DD atau DD/MM/YYYY.</li>
                <li>check_in/check_out: format HH:MM (opsional).</li>
                <li>status: Hadir, Sakit, Izin, Cuti, Terlambat, Absen (opsional, default Hadir).</li>
            </ul>
        </div>
    </div>
</x-app-layout>
