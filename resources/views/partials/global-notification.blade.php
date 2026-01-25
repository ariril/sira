@php
    $suppressAll = (bool)($suppressGlobalNotifications ?? false);
    $suppressErrors = (bool)($suppressGlobalError ?? false);

    $hasValidationError = $errors->any() && !$suppressErrors;

    // Laravel sometimes uses session('status') as a flag (e.g. Breeze profile forms).
    $rawStatus = session('status');
    $statusMap = [
        'profile-updated' => 'Profil berhasil diperbarui.',
        'password-updated' => 'Password berhasil diperbarui.',
        'verification-link-sent' => 'Link verifikasi berhasil dikirim.',
    ];

    $successMessage = session('success') ?? ($success ?? null) ?? (filled($rawStatus) ? ($statusMap[$rawStatus] ?? $rawStatus) : null);
    $errorMessage = session('error') ?? session('danger') ?? ($error ?? null);

    $shouldShow = !$suppressAll && ($hasValidationError || filled($errorMessage) || filled($successMessage));

    $isError = $hasValidationError || filled($errorMessage);

    $message = $hasValidationError
        ? $errors->first()
        : (filled($errorMessage) ? $errorMessage : $successMessage);
@endphp

@if ($shouldShow)
    <div class="container-px mt-4 mb-4 w-full">
        <div class="w-full p-4 rounded-xl border text-sm {{ $isError ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800' }}">
            {{ $message }}
        </div>
    </div>
@endif
