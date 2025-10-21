<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Enums\AboutPageType;
use App\Models\AboutPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AboutPageManageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $items = AboutPage::orderBy('type')->get();
        return view('super_admin.about_pages.index', compact('items'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('super_admin.about_pages.create', [
            'aboutPage' => new AboutPage(),
            'types'     => $this->typeOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        // enforce unique per type
        if (AboutPage::where('type', $data['type'])->exists()) {
            return back()->withErrors(['type' => 'Tipe ini sudah ada. Gunakan edit untuk memperbarui.'])->withInput();
        }

        // uploads
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('about_pages', 'public');
        }
        $data['attachments'] = $this->storeAttachments($request);

        AboutPage::create($data);

        return redirect()->route('super_admin.about-pages.index')
            ->with('status', 'Halaman profil berhasil dibuat.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AboutPage $aboutPage): View
    {
        return view('super_admin.about_pages.edit', [
            'aboutPage' => $aboutPage,
            'types'     => $this->typeOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AboutPage $aboutPage): RedirectResponse
    {
        $data = $this->validateData($request, $aboutPage->id);

        // unique type except self
        if ($data['type'] !== $aboutPage->type?->value && AboutPage::where('type', $data['type'])->exists()) {
            return back()->withErrors(['type' => 'Tipe ini sudah ada.'])->withInput();
        }

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('about_pages', 'public');
        }

        $existing = $aboutPage->attachments ?? [];
        $new      = $this->storeAttachments($request);
        if (!empty($new)) {
            $data['attachments'] = array_values(array_merge($existing, $new));
        }

        $aboutPage->update($data);

        return redirect()->route('super_admin.about-pages.index')
            ->with('status', 'Halaman profil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AboutPage $aboutPage): RedirectResponse
    {
        $aboutPage->delete();
        return back()->with('status', 'Data dihapus.');
    }

    // ===== Helpers =====
    protected function typeOptions(): array
    {
        return collect(AboutPageType::cases())
            ->mapWithKeys(fn($c) => [$c->value => \Illuminate\Support\Str::headline(str_replace('_',' ',$c->value))])
            ->all();
    }

    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        $typeValues = implode(',', array_map(fn($c)=>$c->value, AboutPageType::cases()));

        return $request->validate([
            'type'         => ['required', "in:$typeValues"],
            'title'        => ['nullable','string','max:200'],
            'content'      => ['nullable','string'],
            'image'        => ['sometimes','file','image','max:4096'],
            'attachments'  => ['sometimes','array'],
            'attachments.*'=> ['file','max:20480'],
            'published_at' => ['nullable','date'],
            'is_active'    => ['sometimes','boolean'],
        ]);
    }

    protected function storeAttachments(Request $request): array
    {
        $files = $request->file('attachments', []);
        $paths = [];
        foreach ($files as $file) {
            if ($file) {
                $paths[] = $file->store('about_pages', 'public');
            }
        }
        return $paths;
    }
}
