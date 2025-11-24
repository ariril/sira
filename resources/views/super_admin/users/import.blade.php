@extends('layouts.app')

@section('content')
<div class="container-px py-6 space-y-6 max-w-6xl mx-auto">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-800">Import Pengguna</h1>
        <div class="flex gap-3">
            <a href="{{ route('super_admin.users.import.template') }}" class="btn-blue-grad h-12 px-5 inline-flex items-center rounded-xl text-sm font-medium">
                <i class="fa-solid fa-download mr-2"></i> Download Template
            </a>
            <a href="{{ route('super_admin.users.index') }}" class="btn-blue-grad h-12 px-5 inline-flex items-center rounded-xl text-sm font-medium">
                <i class="fa-solid fa-users mr-2"></i> Daftar Pengguna
            </a>
        </div>
    </div>

    @if(isset($error))
        <div class="bg-rose-50 border border-rose-100 text-rose-700 rounded-xl px-4 py-3 text-sm">{{ $error }}</div>
    @endif
    @if($errors->any())
        <div class="bg-rose-50 border border-rose-100 text-rose-700 rounded-xl px-4 py-3 text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Upload Excel / CSV</h2>
        <p class="text-sm text-slate-600 mb-4">Format header: <code>name,email,roles,profession_id,employee_number,unit_id,password</code>. Kolom opsional dapat dikosongkan. <code>roles</code> berisi slug dipisah koma (contoh: <code>super_admin,admin_rs</code>). Jika kolom <code>password</code> kosong akan dibuatkan otomatis acak 12 karakter.</p>
        <form action="{{ route('super_admin.users.import.process') }}" method="POST" enctype="multipart/form-data" class="space-y-6" id="user-import-form">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-2">File Excel/CSV</label>
                <label id="drop-zone" class="flex flex-col items-center justify-center w-full h-44 border-2 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-slate-100 border-slate-200 transition">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6 text-slate-600">
                        <i class="fa-solid fa-file-excel text-4xl mb-3 text-indigo-500"></i>
                        <p class="text-sm"><span class="font-semibold">Klik untuk pilih</span> atau tarik & lepas</p>
                        <p class="text-xs text-slate-500">.xlsx, .xls, .csv • Maks. 5 MB</p>
                    </div>
                    <input type="file" name="file" id="file-input" accept=".csv,.xlsx,.xls,text/csv" required class="hidden">
                </label>
                <p class="mt-2 text-xs text-slate-600" id="selected-file"></p>
                @error('file')<div class="text-xs text-rose-600 mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn-blue-grad h-12 px-6 inline-flex items-center rounded-xl text-sm font-medium">
                    <i class="fa-solid fa-file-arrow-up mr-2"></i> Unggah & Import
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <h2 class="text-base font-semibold text-slate-800 mb-3">Contoh CSV</h2>
        <pre class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-[13px] overflow-auto">name,email,roles,profession_id,employee_number,unit_id,password
Admin Baru,admin_baru@example.com,super_admin,,ADM001,,password
Perawat A,perawat.a@example.com,pegawai_medis,3,PRW123,12,password
Kepala Unit B,kepala.unitb@example.com,kepala_unit,5,KUB555,7,password</pre>
        <ul class="mt-3 text-sm text-slate-600 list-disc list-inside">
            <li>Baris pertama wajib header. Kolom opsional boleh dikosongkan.</li>
            <li>Jika kolom <code>roles</code> kosong user tidak akan memiliki peran kecuali ada logic default di backend.</li>
            <li>Jika kolom <code>password</code> kosong maka password acak akan dibuat.</li>
            <li><code>profession_id</code> dan <code>unit_id</code> mengacu pada ID yang valid di tabel terkait.</li>
        </ul>
    </div>
</div>
<script>
    (function(){
        const input = document.getElementById('file-input');
        const zone = document.getElementById('drop-zone');
        const info = document.getElementById('selected-file');
        function showFileName(){
            if(input.files.length){
                info.textContent = 'Dipilih: ' + input.files[0].name;
            } else { info.textContent = ''; }
        }
        zone.addEventListener('click', ()=> input.click());
        input.addEventListener('change', showFileName);
        zone.addEventListener('dragover', e=>{e.preventDefault(); zone.classList.add('ring-2','ring-indigo-400');});
        zone.addEventListener('dragleave', e=>{zone.classList.remove('ring-2','ring-indigo-400');});
        zone.addEventListener('drop', e=>{e.preventDefault(); zone.classList.remove('ring-2','ring-indigo-400'); input.files = e.dataTransfer.files; showFileName();});
    })();
</script>
@endsection
