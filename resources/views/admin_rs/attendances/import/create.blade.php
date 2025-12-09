<x-app-layout title="Upload Excel Absensi">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Upload Excel Absensi</h1>
            <x-ui.button as="a" href="{{ route('admin_rs.attendances.batches') }}" variant="success" class="h-12 px-6 text-base">
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
                    <label class="block text-sm font-medium text-slate-600 mb-2">File Excel/CSV</label>
                    <label id="dropzone" class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-slate-100 border-slate-200 transition duration-200">
                        <div id="drop-label" class="flex flex-col items-center justify-center pt-5 pb-6 text-slate-600 transition duration-200">
                            <i id="drop-icon" class="fa-solid fa-file-excel text-3xl mb-2 text-emerald-500 transition duration-200"></i>
                            <p class="text-sm" id="drop-default"><span class="font-semibold">Klik untuk pilih</span> atau tarik & lepas</p>
                            <p class="text-sm hidden text-center" id="drop-selected">
                                <span class="font-semibold">File dipilih:</span>
                                <span id="drop-selected-name"></span>
                                <span class="block text-xs text-slate-500 mt-1">Klik untuk pilih ulang atau tarik & lepas file lainnya</span>
                            </p>
                            <p class="text-xs text-slate-500">.xlsx, .xls, .csv • Maks. 5 MB</p>
                        </div>
                        <input id="file" type="file" name="file" accept=".xlsx,.xls,.csv,text/csv" class="hidden" required />
                    </label>
                    <p class="mt-2 text-xs text-slate-500" id="file-name"></p>
                    <script>
                        document.addEventListener('DOMContentLoaded', function(){
                            const input = document.getElementById('file');
                            const nameEl = document.getElementById('file-name');
                            const defaultPrompt = document.getElementById('drop-default');
                            const selectedPrompt = document.getElementById('drop-selected');
                            const selectedName = document.getElementById('drop-selected-name');
                            const dropzone = document.getElementById('dropzone');
                            const dropLabel = document.getElementById('drop-label');
                            const dropIcon = document.getElementById('drop-icon');
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
                </div>
                <div class="flex items-start gap-3">
                    <input type="checkbox" id="replace_existing" name="replace_existing" value="1" class="mt-1">
                    <label for="replace_existing" class="text-sm text-slate-700">Timpa import pada periode yang sama bila sudah ada. Pastikan file ini lengkap untuk seluruh pegawai.</label>
                </div>
                <div class="flex justify-end">
                    <x-ui.button type="submit" variant="success" class="h-12 px-6 text-base">
                        <i class="fa-solid fa-file-arrow-up mr-2"></i> Unggah & Import
                    </x-ui.button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <h2 class="text-base font-semibold text-slate-800 mb-3">Format Kolom</h2>
            <p class="text-sm text-slate-600 mb-4">Sistem mendukung dua format header: sederhana (EN) atau lengkap (ID). Pilih salah satu.</p>
            <pre class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-[13px] overflow-auto">employee_number,attendance_date,check_in,check_out,status
01234,2025-10-01,08:00,16:00,Hadir
01234,2025-10-02,08:05,16:10,Terlambat
04567,01/10/2025,07:50,16:00,Hadir</pre>
            <pre class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-[13px] overflow-auto mt-3">PIN,NIP,Nama,Jabatan,Ruangan,Periode Mulai,Periode Selesai,Tanggal,Nama Shift,Jam Masuk,Scan Masuk,Datang Terlambat,Jam Keluar,Scan Keluar,Pulang Awal,Durasi Kerja,Istirahat Durasi,Istirahat Lebih,Lembur Akhir,Libur Umum,Libur Rutin,Shift Lembur,Keterangan</pre>
            <ul class="mt-3 text-sm text-slate-600 list-disc list-inside">
                <li>Tanggal: boleh "Wednesday 01-01-2025" atau 01-01-2025.</li>
                <li>Scan Masuk/Keluar dipakai sebagai jam masuk/keluar; Jam Masuk/Keluar disimpan sebagai jadwal.</li>
                <li>Durasi/terlambat/pulang awal dalam HH:MM akan dikonversi ke menit.</li>
                <li>Libur Umum/Rutin/Shift Lembur: isi 1 untuk ya.</li>
            </ul>
        </div>
    </div>
</x-app-layout>
