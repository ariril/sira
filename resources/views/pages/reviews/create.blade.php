@extends('layouts.public')

@section('title','Berikan Ulasan Pelayanan')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-10">
    @if (session('status'))
        <div class="mb-6 rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3">
            {{ session('status') }}
        </div>
    @endif

    <h1 class="text-2xl font-semibold mb-2">Berikan Ulasan Pelayanan</h1>
    <p class="text-slate-600 mb-6">Kami sangat menghargai masukan Anda untuk meningkatkan kualitas layanan. Ulasan dapat ditujukan per orang (dokter/perawat tertentu) atau keseluruhan unit.</p>

    @if ($errors->any())
        <div class="mb-6 rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3">
            <div class="font-medium mb-1">Terdapat kesalahan pada formulir:</div>
            <ul class="list-disc ml-5 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('reviews.store') }}" id="reviewForm" class="bg-white rounded-2xl shadow-md border border-slate-200 p-6 space-y-6">
        @csrf

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Jenis Ulasan</label>
            <div class="flex items-center gap-6">
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="type" value="individual" class="h-4 w-4" {{ old('type','individual')==='individual' ? 'checked' : '' }}>
                    <span>Per Orang</span>
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="type" value="overall" class="h-4 w-4" {{ old('type')==='overall' ? 'checked' : '' }}>
                    <span>Keseluruhan Unit</span>
                </label>
            </div>
        </div>

        <div id="unitField" class="hidden">
            <label for="unit_id" class="block text-xs font-medium text-slate-600 mb-1">Pilih Unit</label>
            <div class="relative">
                <select id="unit_id" name="unit_id" class="w-full h-12 pl-4 pr-10 rounded-xl border-slate-300 text-[15px] shadow-sm appearance-none focus:border-blue-500 focus:ring-blue-500">
                    <option value="">-- Pilih Unit --</option>
                    @foreach ($units as $u)
                        <option value="{{ $u->id }}" {{ old('unit_id')==$u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
                <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            </div>
        </div>

        <div id="staffField">
            <label for="staff_id" class="block text-xs font-medium text-slate-600 mb-1">Pilih Pegawai Medis</label>
            <div class="relative">
                <select id="staff_id" name="staff_id" class="w-full h-12 pl-4 pr-10 rounded-xl border-slate-300 text-[15px] shadow-sm appearance-none focus:border-blue-500 focus:ring-blue-500">
                    <option value="">-- Pilih Pegawai --</option>
                    @foreach ($staff as $s)
                        @php $unitName = optional($units->firstWhere('id',$s->unit_id))->name; @endphp
                        <option value="{{ $s->id }}" data-unit="{{ $s->unit_id }}" {{ old('staff_id')==$s->id ? 'selected' : '' }}>
                            {{ $s->name }} @if($unitName) ({{ $unitName }}) @endif
                        </option>
                    @endforeach
                </select>
                <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            </div>
            <p class="text-xs text-slate-500 mt-1">Opsional: pilih unit terlebih dahulu agar daftar pegawai tersaring.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="rating" class="block text-xs font-medium text-slate-600 mb-1">Penilaian</label>
                <x-ui.select id="rating" name="rating" :options="[1=>1,2=>2,3=>3,4=>4,5=>5]" :value="old('rating')" placeholder="-- Pilih nilai 1-5 --" required />
            </div>
            <div>
                <label for="patient_name" class="block text-xs font-medium text-slate-600 mb-1">Nama (opsional)</label>
                <x-ui.input id="patient_name" name="patient_name" :value="old('patient_name')" placeholder="Boleh dikosongkan" />
            </div>
            <div class="sm:col-span-2">
                <label for="contact" class="block text-xs font-medium text-slate-600 mb-1">Kontak (opsional)</label>
                <x-ui.input id="contact" name="contact" :value="old('contact')" placeholder="No. HP / email (opsional)" />
            </div>
        </div>

        <div>
            <label for="comment" class="block text-xs font-medium text-slate-600 mb-1">Ulasan</label>
            <x-ui.textarea id="comment" name="comment" rows="4" :value="old('comment')" required />
            <p class="text-xs text-slate-500 mt-1">Tulis pengalaman Anda dengan singkat dan jelas.</p>
        </div>

        <div class="pt-2 flex items-center gap-3">
            <x-ui.button type="submit">
                <i class="fa-solid fa-paper-plane"></i> Kirim Ulasan
            </x-ui.button>
            <x-ui.button as="a" href="{{ route('home') }}" variant="outline">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke beranda
            </x-ui.button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function(){
    const typeRadios = document.querySelectorAll('input[name="type"]');
    const unitField = document.getElementById('unitField');
    const staffField = document.getElementById('staffField');
    const unitSelect = document.getElementById('unit_id');
    const staffSelect = document.getElementById('staff_id');

    function applyVisibility() {
        const type = document.querySelector('input[name="type"]:checked')?.value;
        if (type === 'overall') {
            unitField.classList.remove('hidden');
            staffField.classList.add('hidden');
        } else {
            unitField.classList.add('hidden');
            staffField.classList.remove('hidden');
        }
    }

    function filterStaffByUnit() {
        const uid = unitSelect.value;
        [...staffSelect.options].forEach(opt => {
            if (!opt.value) return; // skip placeholder
            const belongs = !uid || (opt.dataset.unit === uid);
            opt.hidden = !belongs;
        });
        // If selected option is hidden, reset
        if (staffSelect.selectedOptions[0] && staffSelect.selectedOptions[0].hidden) {
            staffSelect.value = '';
        }
    }

    typeRadios.forEach(r => r.addEventListener('change', applyVisibility));
    unitSelect.addEventListener('change', filterStaffByUnit);

    // initial state
    applyVisibility();
    filterStaffByUnit();
})();
</script>
@endpush
