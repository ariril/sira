<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Remunerasi</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111827; }
        .title { font-size: 16px; font-weight: bold; margin-bottom: 6px; }
        .subtitle { font-size: 11px; color: #6b7280; margin-bottom: 12px; }
        .filters { margin-bottom: 12px; }
        .filters td { padding: 2px 6px; vertical-align: top; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; }
        th { background: #f3f4f6; text-align: left; }
        .text-right { text-align: right; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <div class="title">Daftar Remunerasi</div>
    <div class="subtitle">Dicetak: {{ $printedAt?->format('d/m/Y H:i') }}</div>

    <table class="filters">
        <tr>
            <td class="muted">Periode</td>
            <td>: {{ $filters['period'] ?? '-' }}</td>
            <td class="muted">Unit</td>
            <td>: {{ $filters['unit'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="muted">Profesi</td>
            <td>: {{ $filters['profession'] ?? '-' }}</td>
            <td class="muted">Status Publikasi</td>
            <td>: {{ $filters['published'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="muted">Status Pembayaran</td>
            <td>: {{ $filters['payment_status'] ?? '-' }}</td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 36px;">No</th>
                <th>Nama</th>
                <th>Unit</th>
                <th>Profesi</th>
                <th>Periode</th>
                <th class="text-right">Jumlah Remunerasi</th>
                <th>Status Publikasi</th>
                <th>Status Pembayaran</th>
                <th>Tanggal Bayar</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $i => $it)
                <tr>
                    <td class="text-right">{{ $i + 1 }}</td>
                    <td>{{ $it->user->name ?? '-' }}</td>
                    <td>{{ $it->user->unit->name ?? '-' }}</td>
                    <td>{{ $it->user->profession->name ?? '-' }}</td>
                    <td>{{ $it->assessmentPeriod->name ?? '-' }}</td>
                    <td class="text-right">{{ number_format((float) ($it->amount ?? 0), 2) }}</td>
                    <td>{{ !empty($it->published_at) ? 'Dipublikasikan' : 'Draft' }}</td>
                    <td>{{ $it->payment_status?->value ?? ($it->payment_status ?? '-') }}</td>
                    <td>{{ !empty($it->payment_date) ? $it->payment_date->format('d/m/Y') : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center muted">Tidak ada data remunerasi.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="muted" style="margin-top: 10px;">
        Catatan: Dokumen ini menampilkan ringkasan remunerasi sesuai dengan tampilan di modul.
    </p>
</body>
</html>
