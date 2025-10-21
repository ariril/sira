@php $site = \App\Models\SiteSetting::first(); @endphp
<footer class="mt-10 border-t bg-white/70">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 text-sm text-slate-500 flex flex-col md:flex-row gap-3 md:items-center md:justify-between">
        <div class="leading-5">
            {!! $site?->footer_text
                ? e($site->footer_text)
                : '© '.now()->year.' RSUD Mgr. Gabriel Manek, SVD Atambua. Semua hak cipta.' !!}
        </div>
        <div class="flex items-center gap-4">
            @if($site?->facebook_url)
                <a href="{{ $site->facebook_url }}" class="inline-flex items-center gap-1 hover:text-slate-700" target="_blank" rel="noreferrer">
                    <i class="fa-brands fa-facebook"></i> Facebook
                </a>
            @endif
            @if($site?->instagram_url)
                <a href="{{ $site->instagram_url }}" class="inline-flex items-center gap-1 hover:text-slate-700" target="_blank" rel="noreferrer">
                    <i class="fa-brands fa-instagram"></i> Instagram
                </a>
            @endif
            @if($site?->youtube_url)
                <a href="{{ $site->youtube_url }}" class="inline-flex items-center gap-1 hover:text-slate-700" target="_blank" rel="noreferrer">
                    <i class="fa-brands fa-youtube"></i> YouTube
                </a>
            @endif
        </div>
    </div>
</footer>
