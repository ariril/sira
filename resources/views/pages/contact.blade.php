@extends('layouts.public')
@section('title', 'Kontak - RSUD MGR GM')

@section('content')
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-12">
        <h1 class="text-3xl font-semibold text-slate-800 mb-6">Kontak</h1>

        <div class="grid gap-8 md:grid-cols-2">
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-11 h-11 rounded-xl grid place-content-center bg-blue-600 text-white">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>
                    <div>
                        <div class="text-sm text-slate-500">Alamat</div>
                        <div class="font-medium text-slate-800">{{ $site?->address ?? '—' }}</div>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-11 h-11 rounded-xl grid place-content-center bg-blue-600 text-white">
                        <i class="fa-solid fa-phone"></i>
                    </div>
                    <div>
                        <div class="text-sm text-slate-500">Telepon</div>
                        <div class="font-medium text-slate-800">{{ $site?->phone ?? '—' }}</div>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-11 h-11 rounded-xl grid place-content-center bg-blue-600 text-white">
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                    <div>
                        <div class="text-sm text-slate-500">Email</div>
                        <div class="font-medium text-slate-800">{{ $site?->email ?? '—' }}</div>
                    </div>
                </div>

                @if($site?->short_description)
                    <div class="pt-2 text-slate-600">{!! nl2br(e($site->short_description)) !!}</div>
                @endif
            </div>

            <div>
                <div class="bg-white rounded-xl shadow p-4 h-full">
                    <div class="w-full rounded-lg overflow-hidden border border-slate-200 bg-slate-100 h-[360px] md:h-[460px]">
                        <iframe
                            title="Peta Lokasi RSUD GM Atambua"
                            src="https://www.google.com/maps?q=-9.0985731,124.896737&hl=id&z=18&output=embed"
                            width="100%"
                            height="100%"
                            style="border:0;"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                    <div class="flex items-center justify-between mt-3">
                        <p class="text-xs text-slate-500">Hubungi kami melalui telepon atau email pada jam kerja.</p>
                        <a class="text-blue-600 text-sm hover:underline" target="_blank" rel="noopener"
                           href="https://www.google.com/maps?q=WV2W%2BGVV%2C+Tenukiik%2C+Berdao%2C+Kec.+Atambua+Bar.%2C+Kabupaten+Belu%2C+Nusa+Tenggara+Tim.">
                            Buka di Google Maps
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
