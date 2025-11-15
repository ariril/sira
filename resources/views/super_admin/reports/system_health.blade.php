@php
    $statusColor = [
        'ok' => 'text-green-700 bg-green-50 ring-green-600/20',
        'warn' => 'text-amber-700 bg-amber-50 ring-amber-600/20',
        'fail' => 'text-red-700 bg-red-50 ring-red-600/20',
    ];
@endphp

<x-app-layout title="Kesehatan Sistem">
    <div class="max-w-5xl mx-auto py-8">
        <h1 class="text-2xl font-semibold mb-6">Kesehatan Sistem</h1>

        <div class="grid md:grid-cols-2 gap-6">
            <div class="space-y-2">
                <h2 class="text-lg font-medium">Summary</h2>
                <div class="rounded border bg-white p-4 text-sm">
                    <dl class="divide-y divide-gray-100">
                        @foreach($summary as $k => $v)
                            <div class="flex items-center justify-between py-2">
                                <dt class="text-gray-500">{{ str($k)->headline() }}</dt>
                                <dd class="font-medium">{{ is_bool($v) ? ($v ? 'true' : 'false') : $v }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>

            <div class="space-y-2">
                <h2 class="text-lg font-medium">Checks</h2>
                <div class="rounded border bg-white p-4 text-sm divide-y">
                    @foreach($checks as $check)
                        <div class="py-3 flex items-center justify-between">
                            <div>
                                <div class="font-medium">{{ $check['name'] }}</div>
                                <div class="text-gray-500">{{ $check['message'] }}</div>
                            </div>
                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $statusColor[$check['status']] ?? 'bg-gray-100 text-gray-800 ring-gray-300' }}">
                                {{ strtoupper($check['status']) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-8 text-gray-500 text-sm">
            <p>Halaman ini membantu Super Admin memverifikasi dengan cepat bahwa aplikasi sudah dikonfigurasi dengan benar (database, cache, penyimpanan, dan lainnya).</p>
        </div>
    </div>
</x-app-layout>
