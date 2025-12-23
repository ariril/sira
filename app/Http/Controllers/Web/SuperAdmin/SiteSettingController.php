<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteSettingController extends Controller
{
    /** Index: tampilkan form pengaturan situs */
    public function index(): View
    {
        $setting = SiteSetting::first() ?? new SiteSetting();
        return view('super_admin.site_settings.index', compact('setting'));
    }

    public function create()
    {
        abort(404);
    }

    public function store(Request $request)
    {
        abort(404);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        abort(404);
    }

    /** Update pengaturan (single record) */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'              => ['required','string','max:150'],
            'short_name'        => ['nullable','string','max:50'],
            'short_description' => ['nullable','string'],
            'address'           => ['nullable','string'],
            'phone'             => ['nullable','string','max:30'],
            'email'             => ['nullable','email','max:150'],
            'timezone'          => ['required','string', Rule::in($this->timezoneOptions())],
            'facebook_url'      => ['nullable','url'],
            'instagram_url'     => ['nullable','url'],
            'twitter_url'       => ['nullable','url'],
            'youtube_url'       => ['nullable','url'],
            'footer_text'       => ['nullable','string','max:255'],
            'logo'              => ['sometimes','file','image','max:4096'],
            'favicon'           => ['sometimes','file','mimes:ico,png','max:1024'],
            'hero'              => ['sometimes','file','image','max:6144'],
        ]);

        $setting = SiteSetting::first() ?? new SiteSetting();

        // uploads
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('site', 'public');
        }
        if ($request->hasFile('favicon')) {
            $data['favicon_path'] = $request->file('favicon')->store('site', 'public');
        }
        if ($request->hasFile('hero')) {
            $data['hero_path'] = $request->file('hero')->store('site', 'public');
        }

        $setting->fill($data);
        $setting->updated_by = Auth::id();
        $setting->save();

        Cache::forget('site.timezone');
        Cache::forget('site.profile');

        return redirect()->route('super_admin.site-settings.index')
            ->with('status','Pengaturan situs diperbarui.');
    }

    /** Daftar timezone yang diizinkan untuk super admin. */
    private function timezoneOptions(): array
    {
        return [
            'Asia/Jakarta',   // UTC+7
            'Asia/Makassar',  // UTC+8
            'Asia/Jayapura',  // UTC+9
            'UTC',
        ];
    }

    public function destroy(string $id)
    {
        abort(404);
    }
}
