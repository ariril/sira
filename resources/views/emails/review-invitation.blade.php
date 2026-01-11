@php
    /** @var \App\Models\ReviewInvitation $invitation */
    $appName = config('app.name');
    $patient = $invitation->patient_name ?: 'Pasien';
    $unit = $invitation->unit?->name ?: '-';
@endphp

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Undangan Survei Kepuasan</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
            <div style="padding:20px 24px;border-bottom:1px solid #e2e8f0;">
                <div style="font-size:16px;font-weight:700;color:#0f172a;">{{ $appName }}</div>
                <div style="font-size:13px;color:#64748b;margin-top:4px;">Undangan Survei Kepuasan Pelayanan</div>
            </div>

            <div style="padding:24px;">
                <p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;color:#0f172a;">
                    Yth. {{ $patient }},
                </p>

                <p style="margin:0 0 14px 0;font-size:14px;line-height:1.6;color:#0f172a;">
                    Terima kasih telah menggunakan layanan kami di <strong>{{ $unit }}</strong>.
                    Kami mohon kesediaannya untuk mengisi survei kepuasan agar kualitas pelayanan dapat terus ditingkatkan.
                </p>

                <div style="margin:22px 0;">
                    <a href="{{ $shortUrl }}" style="display:inline-block;background:#16a34a;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:12px;font-weight:700;font-size:14px;">
                        Isi Survei Kepuasan
                    </a>
                </div>

                <p style="margin:0;font-size:12px;line-height:1.6;color:#64748b;">
                    Jika tombol tidak berfungsi, buka tautan berikut:
                    <br>
                    <a href="{{ $shortUrl }}" style="color:#4f46e5;word-break:break-all;">{{ $shortUrl }}</a>
                </p>
            </div>

            <div style="padding:16px 24px;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">
                Email ini dikirim otomatis. Mohon tidak membalas email ini.
            </div>
        </div>
    </div>
</body>
</html>
