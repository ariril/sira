@php /** @var \App\Models\SiteSetting $setting */ @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div class="space-y-4">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Nama Situs *</label>
            <x-ui.input name="name" :value="old('name', $setting->name)" required />
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Singkatan</label>
                <x-ui.input name="short_name" :value="old('short_name', $setting->short_name)" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Email</label>
                <x-ui.input type="email" name="email" :value="old('email', $setting->email)" />
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Deskripsi Singkat</label>
            <x-ui.textarea name="short_description" rows="3" :value="old('short_description', $setting->short_description)" />
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Alamat</label>
            <x-ui.textarea name="address" rows="3" :value="old('address', $setting->address)" />
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Telepon</label>
                <x-ui.input name="phone" :value="old('phone', $setting->phone)" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Teks Footer</label>
                <x-ui.input name="footer_text" :value="old('footer_text', $setting->footer_text)" />
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Logo</label>
                <x-ui.input id="logo" type="file" name="logo" accept="image/*" />
                <div class="mt-2">
                    @php
                        $logoUrl = $setting->logo_path
                            ? Storage::url($setting->logo_path)
                            : Storage::url('images/logo-rsudmgr.jpeg');
                    @endphp
                    <img id="preview-logo" src="{{ $logoUrl }}" alt="Logo Saat Ini" class="h-16 rounded border bg-white object-contain">
                    <div class="mt-1 text-xs text-slate-500">{{ $setting->logo_path ?: 'images/logo-rsudmgr.jpeg' }}</div>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Favicon</label>
                <x-ui.input id="favicon" type="file" name="favicon" accept="image/x-icon,image/png,.ico,.png" />
                @if($setting->favicon_path)
                    <div class="mt-2">
                        <img id="preview-favicon" src="{{ Storage::url($setting->favicon_path) }}" alt="Favicon Saat Ini" class="h-10 w-10 rounded border bg-white object-contain">
                        <div class="mt-1 text-xs text-slate-500">{{ $setting->favicon_path }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Gambar Hero (Homepage)</label>
            <x-ui.input id="hero" type="file" name="hero" accept="image/*" />
            <div class="mt-2">
                @php
                    $heroUrl = $setting->hero_path
                        ? Storage::url($setting->hero_path)
                        : Storage::url('images/hero.jpeg');
                @endphp
                <img id="preview-hero" src="{{ $heroUrl }}" alt="Gambar Hero Saat Ini" class="h-28 rounded border bg-white object-cover">
                <div class="mt-1 text-xs text-slate-500">{{ $setting->hero_path ?: 'images/hero.jpeg' }}</div>
            </div>
        </div>

        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const bindPreview = (inputId, imgId) => {
                        const input = document.getElementById(inputId);
                        const img = document.getElementById(imgId);
                        if (!input || !img) return;
                        input.addEventListener('change', (e) => {
                            const file = e.target.files?.[0];
                            if (!file) return;
                            const url = URL.createObjectURL(file);
                            img.src = url;
                        });
                    };
                    bindPreview('logo', 'preview-logo');
                    bindPreview('favicon', 'preview-favicon');
                    bindPreview('hero', 'preview-hero');
                });
            </script>
        @endpush

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Facebook URL</label>
                <x-ui.input name="facebook_url" :value="old('facebook_url', $setting->facebook_url)" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Instagram URL</label>
                <x-ui.input name="instagram_url" :value="old('instagram_url', $setting->instagram_url)" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Twitter URL</label>
                <x-ui.input name="twitter_url" :value="old('twitter_url', $setting->twitter_url)" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">YouTube URL</label>
                <x-ui.input name="youtube_url" :value="old('youtube_url', $setting->youtube_url)" />
            </div>
        </div>

        <div class="pt-2 flex items-center justify-end">
            <x-ui.button type="submit" variant="success">
                <i class="fa-solid fa-floppy-disk"></i>
                Simpan Perubahan
            </x-ui.button>
        </div>
    </div>
</div>
