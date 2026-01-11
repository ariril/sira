@php
    $appName = config('app.name');
@endphp

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test Email</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
            <div style="padding:20px 24px;border-bottom:1px solid #e2e8f0;">
                <div style="font-size:16px;font-weight:700;color:#0f172a;">{{ $appName }}</div>
                <div style="font-size:13px;color:#64748b;margin-top:4px;">Test Email SMTP</div>
            </div>

            <div style="padding:24px;">
                <p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;color:#0f172a;">
                    Ini adalah email test untuk verifikasi pengiriman SMTP dari aplikasi lokal.
                </p>
                <p style="margin:0;font-size:13px;line-height:1.6;color:#64748b;">
                    Tujuan: <strong>{{ $toEmail }}</strong><br>
                    Waktu kirim (app): <strong>{{ $sentAt->format('d M Y H:i:s') }}</strong>
                </p>
            </div>

            <div style="padding:16px 24px;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">
                Email ini dikirim otomatis. Mohon tidak membalas email ini.
            </div>
        </div>
    </div>
</body>
</html>
