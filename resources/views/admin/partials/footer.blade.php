{{-- resources/views/admin/partials/footer.blade.php --}}
@php $site = \App\Models\SiteSetting::first(); @endphp
<footer class="mt-10 border-t bg-white/70">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 text-sm text-slate-500 flex flex-col md:flex-row gap-3 md:items-center md:justify-between">
        <div>
            {!! $site?->teks_footer
                ? e($site->teks_footer)
                : '© '.now()->year.' RSUD Mgr. Gabriel Manek, SVD Atambua. Semua hak cipta.' !!}
        </div>
        <div class="flex items-center gap-4">
            @if($site?->url_facebook)
                <a href="{{ $site->url_facebook }}" class="hover:text-slate-700" target="_blank" rel="noreferrer">Facebook</a>
            @endif
            @if($site?->url_instagram)
                <a href="{{ $site->url_instagram }}" class="hover:text-slate-700" target="_blank" rel="noreferrer">Instagram</a>
            @endif
            @if($site?->url_youtube)
                <a href="{{ $site->url_youtube }}" class="hover:text-slate-700" target="_blank"
                   rel="noreferrer">YouTube</a>
            @endif
        </div>
    </div>
</footer>
