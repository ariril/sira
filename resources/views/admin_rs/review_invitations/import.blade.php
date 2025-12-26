<x-app-layout title="Import Review Invitations">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Import Invitation Link (Excel)</h1>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-3">
            <div class="font-medium">Format file (heading wajib)</div>
            <div class="text-sm text-slate-600">registration_ref | patient_name | phone | unit | staff_numbers</div>
            <div class="text-xs text-slate-500">Contoh: REG-2024-009812 | Maria L. | 08xxx | Poli Umum | D001;P012</div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
            <div class="font-medium">Upload Excel/CSV</div>
            <form method="POST" action="{{ route('admin_rs.review_invitations.import.process') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">File Excel/CSV</label>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv,text/csv" required />
                    @error('file')
                        <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <x-ui.button type="submit" class="h-11 px-5">Proses Import</x-ui.button>
            </form>
        </div>

        @if($summary)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 flex flex-wrap items-center justify-between gap-4">
                <div class="text-sm text-slate-700">
                    <span class="font-semibold">Sukses:</span> {{ $summary['success'] ?? 0 }}
                    <span class="mx-2">•</span>
                    <span class="font-semibold">Gagal:</span> {{ $summary['failed'] ?? 0 }}
                    <span class="mx-2">•</span>
                    <span class="font-semibold">Skip:</span> {{ $summary['skipped'] ?? 0 }}
                </div>

                <div>
                    <a href="{{ route('admin_rs.review_invitations.import.export') }}" class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-2xl text-sm font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200">
                        Export CSV (Sukses)
                    </a>
                </div>
            </div>
        @endif

        @if(!empty($results))
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
                <div class="font-medium">Hasil Import</div>

                <div class="overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-slate-500 border-b">
                                <th class="py-2 pr-4">Row</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Registration Ref</th>
                                <th class="py-2 pr-4">Patient</th>
                                <th class="py-2 pr-4">Contact</th>
                                <th class="py-2 pr-4">Unit</th>
                                <th class="py-2 pr-4">Alasan</th>
                                <th class="py-2 pr-4">Link Undangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($results as $r)
                                <tr>
                                    <td class="py-2 pr-4">{{ $r['row'] ?? '' }}</td>
                                    <td class="py-2 pr-4">
                                        <span class="font-semibold">
                                            {{ strtoupper((string)($r['status'] ?? '')) }}
                                        </span>
                                    </td>
                                    <td class="py-2 pr-4">{{ $r['registration_ref'] ?? '-' }}</td>
                                    <td class="py-2 pr-4">{{ $r['patient_name'] ?? '-' }}</td>
                                    <td class="py-2 pr-4">{{ $r['contact'] ?? '-' }}</td>
                                    <td class="py-2 pr-4">{{ $r['unit'] ?? '-' }}</td>
                                    <td class="py-2 pr-4 text-slate-600">{{ $r['message'] ?? '' }}</td>
                                    <td class="py-2 pr-4">
                                        @if(!empty($r['link_undangan']))
                                            <a class="text-indigo-600 hover:underline" href="{{ $r['link_undangan'] }}" target="_blank" rel="noreferrer">{{ $r['link_undangan'] }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
