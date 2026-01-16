<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Remunerasi Saya</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111827; }
        .title { font-size: 16px; font-weight: bold; margin-bottom: 6px; }
        .subtitle { font-size: 11px; color: #6b7280; margin-bottom: 12px; }
        .grid { width: 100%; margin-bottom: 10px; }
        .grid td { padding: 4px 6px; vertical-align: top; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; }
        th { background: #f3f4f6; text-align: left; }
        .text-right { text-align: right; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <div class="title">Remunerasi Saya</div>
    <div class="subtitle">Dicetak: {{ $printedAt?->format('d/m/Y H:i') }}</div>

    <table class="grid">
        <tr>
            <td class="muted">Nama</td>
            <td>: {{ $item->user->name ?? '-' }}</td>
            <td class="muted">Periode</td>
            <td>: {{ $item->assessmentPeriod->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="muted">Unit</td>
            <td>: {{ $unitName ?? '-' }}</td>
            <td class="muted">Profesi</td>
            <td>: {{ $professionName ?? '-' }}</td>
        </tr>
        <tr>
            <td class="muted">Jumlah Remunerasi</td>
            <td>: {{ $item->amount !== null ? 'Rp '.number_format($item->amount,0,',','.') : '-' }}</td>
            <td class="muted">Status Pembayaran</td>
            <td>: {{ $item->payment_status?->value ?? ($item->payment_status ?? '-') }}</td>
        </tr>
        <tr>
            <td class="muted">Dipublikasikan</td>
            <td>: {{ optional($item->published_at)->format('d M Y H:i') ?? '-' }}</td>
            <td class="muted">Alokasi</td>
            <td>: {{ isset($allocationAmount) ? 'Rp '.number_format((float)$allocationAmount,0,',','.') : '-' }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Ringkasan Nominal</th>
                <th class="text-right">Nilai</th>
            </tr>
        </thead>
        <tbody>
            @php
                $criteriaAllocations = $criteriaAllocations ?? [];
            @endphp
            @if(!empty($criteriaAllocations))
                @foreach($criteriaAllocations as $ca)
                    <tr>
                        <td>{{ $ca['criteria_name'] ?? '-' }}</td>
                        <td class="text-right">{{ 'Rp '.number_format((float)($ca['nominal'] ?? 0),0,',','.') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td><strong>Total</strong></td>
                    <td class="text-right"><strong>{{ $item->amount !== null ? 'Rp '.number_format($item->amount,0,',','.') : '-' }}</strong></td>
                </tr>
            @else
                <tr>
                    <td colspan="2" class="muted">Tidak ada rincian nominal.</td>
                </tr>
            @endif
        </tbody>
    </table>

    <p class="muted" style="margin-top: 10px;">
        Catatan: Dokumen ini menampilkan ringkasan remunerasi tanpa istilah teknis agar mudah dipahami.
    </p>
</body>
</html>
