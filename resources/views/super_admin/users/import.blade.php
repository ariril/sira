<x-app-layout title="Import Pengguna">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Import Pengguna</h1>
            <div class="flex items-center gap-3">
                <x-ui.button as="a" href="{{ route('super_admin.users.import.template') }}" variant="outline" class="h-12 px-5 text-base">
                    <i class="fa-solid fa-download mr-2"></i> Download Template
                </x-ui.button>
                <x-ui.button as="a" href="{{ route('super_admin.users.index') }}" variant="primary" class="h-12 px-6 text-base">
                    <i class="fa-solid fa-users mr-2"></i> Daftar Pengguna
                </x-ui.button>
            </div>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        @if(!empty($error))
            <div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-800 px-4 py-3 text-sm">
                {{ $error }}
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Upload Excel / CSV</h2>

        <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
            <div class="text-xs font-semibold text-slate-700">Header wajib (nama kolom harus sama)</div>
            <div class="mt-2 rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-[12px] text-slate-700 break-all">
                name,email,roles,profession_slug,employee_number,unit_slug,password,start_date,gender,nationality,address,phone,last_education,position
            </div>
            <ul class="mt-3 text-sm text-slate-600 list-disc list-inside space-y-1">
                <li>Kolom opsional boleh dikosongkan.</li>
                <li><span class="font-medium">roles</span>: slug dipisah koma (contoh: <code>super_admin,admin_rs</code>).</li>
                <li><span class="font-medium">unit_slug</span>: gunakan slug unit (bukan ID). Jika kosong diset <code>null</code>.</li>
                <li><span class="font-medium">profession_slug</span>: boleh isi kode profesi (<code>professions.code</code>) atau slug dari nama profesi (contoh: <code>dokter-spesialis-anak</code>).</li>
                <li><span class="font-medium">start_date</span>: format <code>YYYY-MM-DD</code>. Jika <span class="font-medium">password</span> kosong, user baru akan dibuatkan password acak 12 karakter.</li>
            </ul>
        </div>
        <form action="{{ route('super_admin.users.import.process') }}" method="POST" enctype="multipart/form-data" class="space-y-6" id="user-import-form">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-2">File Excel/CSV</label>
                <label id="user-dropzone" class="flex flex-col items-center justify-center w-full h-44 border-2 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-slate-100 border-slate-200 transition duration-200">
                    <div id="user-drop-label" class="flex flex-col items-center justify-center pt-5 pb-6 text-slate-600 transition duration-200">
                        <i id="user-drop-icon" class="fa-solid fa-file-excel text-4xl mb-3 text-indigo-500 transition duration-200"></i>
                        <p class="text-sm" id="user-drop-default"><span class="font-semibold">Klik untuk pilih</span> atau tarik & lepas</p>
                        <p class="text-sm hidden text-center" id="user-drop-selected">
                            <span class="font-semibold">File dipilih:</span>
                            <span id="user-drop-selected-name"></span>
                            <span class="block text-xs text-slate-500 mt-1">Klik untuk pilih ulang atau tarik & lepas file lainnya</span>
                        </p>
                        <p class="text-xs text-slate-500">.xlsx, .xls, .csv • Maks. 5 MB</p>
                    </div>
                    <input type="file" name="file" id="user-file" accept=".csv,.xlsx,.xls,text/csv" required class="hidden">
                </label>
                <p class="mt-2 text-xs text-slate-600" id="user-file-name"></p>
                @error('file')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="flex justify-end">
                <x-ui.button type="submit" variant="primary" class="h-12 px-6 text-base">
                    <i class="fa-solid fa-file-arrow-up mr-2"></i> Unggah & Import
                </x-ui.button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <h2 class="text-base font-semibold text-slate-800 mb-3">Contoh CSV</h2>
        <pre class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-[13px] overflow-auto">name,email,roles,profession_slug,employee_number,unit_slug,password,start_date,gender,nationality,address,phone,last_education,position
    Admin Baru,admin_baru@example.com,super_admin,,ADM001,,password,2025-01-02,Laki-laki,Indonesia,Jl. Contoh 1,08123456789,S1,Super Admin
    Perawat A,perawat.a@example.com,pegawai_medis,NRS,PRW123,igd,,2025-03-10,Perempuan,Indonesia,Jl. Contoh 2,08120000000,D3,Perawat
    Kepala Unit B,kepala.unitb@example.com,kepala_unit,DOK,KUB555,poliklinik-bedah,password,2025-04-01,Laki-laki,Indonesia,Jl. Contoh 3,08129999999,S2,Kepala Unit</pre>
        <ul class="mt-3 text-sm text-slate-600 list-disc list-inside">
            <li>Baris pertama wajib header. Kolom opsional boleh dikosongkan.</li>
            <li>Jika kolom <code>roles</code> kosong user tidak akan memiliki peran kecuali ada logic default di backend.</li>
            <li>Jika kolom <code>password</code> kosong maka password acak akan dibuat untuk user baru; untuk user existing password tidak diubah.</li>
            <li><code>profession_slug</code> dan <code>unit_slug</code> harus cocok dengan data master (jika diisi namun tidak ditemukan, baris akan gagal).</li>
        </ul>
    </div>
    </div>
<script>
    document.addEventListener('DOMContentLoaded', function(){
        const input = document.getElementById('user-file');
        const nameEl = document.getElementById('user-file-name');
        const defaultPrompt = document.getElementById('user-drop-default');
        const selectedPrompt = document.getElementById('user-drop-selected');
        const selectedName = document.getElementById('user-drop-selected-name');
        const dropzone = document.getElementById('user-dropzone');
        const dropLabel = document.getElementById('user-drop-label');
        const dropIcon = document.getElementById('user-drop-icon');
        if (!input) return;

        const resetState = () => {
            if (defaultPrompt) defaultPrompt.classList.remove('hidden');
            if (selectedPrompt) selectedPrompt.classList.add('hidden');
            if (selectedName) selectedName.textContent = '';
            if (nameEl) nameEl.textContent = '';
            if (dropzone) {
                dropzone.classList.remove('bg-blue-50','border-blue-300','shadow-inner');
                dropzone.classList.add('bg-slate-50','border-slate-200');
            }
            if (dropLabel) {
                dropLabel.classList.remove('text-blue-700');
                dropLabel.classList.add('text-slate-600');
            }
            if (dropIcon) {
                dropIcon.classList.remove('text-blue-600');
                dropIcon.classList.add('text-indigo-500');
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
                    dropzone.classList.add('bg-blue-50','border-blue-300','shadow-inner');
                }
                if (dropLabel) {
                    dropLabel.classList.remove('text-slate-600');
                    dropLabel.classList.add('text-blue-700');
                }
                if (dropIcon) {
                    dropIcon.classList.remove('text-indigo-500');
                    dropIcon.classList.add('text-blue-600');
                }
            } else {
                resetState();
            }
        });

        dropzone?.addEventListener('dragover', (event) => {
            event.preventDefault();
            dropzone.classList.add('border-blue-300');
        });
        dropzone?.addEventListener('dragleave', () => {
            dropzone.classList.remove('border-blue-300');
        });
        dropzone?.addEventListener('drop', (event) => {
            event.preventDefault();
            dropzone.classList.remove('border-blue-300');
            if (!event.dataTransfer) return;
            input.files = event.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        });

        resetState();
    });
</script>
</x-app-layout>
