<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FaqManageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $perPageOptions = [10, 12, 20, 30, 50];

        $data = $request->validate([
            'q'        => ['nullable','string','max:100'],
            'category' => ['nullable','string','max:100'],
            'active'   => ['nullable', Rule::in(['yes','no'])],
            'per_page' => ['nullable','integer','in:' . implode(',', $perPageOptions)],
        ]);

        $q        = $data['q']        ?? null;
        $category = $data['category'] ?? null;
        $active   = $data['active']   ?? null;
        $perPage  = (int)($data['per_page'] ?? 12);

        $faqs = Faq::query()
            ->when($q,        fn($w) => $w->where('question','like',"%{$q}%")->orWhere('answer','like',"%{$q}%"))
            ->when($category, fn($w) => $w->where('category', $category))
            ->when($active === 'yes', fn($w) => $w->where('is_active', true))
            ->when($active === 'no',  fn($w) => $w->where('is_active', false))
            ->orderBy('order')
            ->paginate($perPage)
            ->withQueryString();

        return view('super_admin.faqs.index', compact('faqs'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('super_admin.faqs.create', [
            'faq' => new Faq(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['is_active'] = $request->boolean('is_active');
        Faq::create($data);

        return redirect()->route('super_admin.faqs.index')
            ->with('status','FAQ berhasil dibuat.');
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
    public function edit(Faq $faq): View
    {
        return view('super_admin.faqs.edit', compact('faq'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $data = $this->validateData($request, $faq->id);
        $data['is_active'] = $request->boolean('is_active');
        $faq->update($data);

        return redirect()->route('super_admin.faqs.index')
            ->with('status','FAQ berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();
        return back()->with('status','FAQ dihapus.');
    }

    // ===== Helpers =====
    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'question' => ['required','string','max:255'],
            'answer'   => ['required','string'],
            'order'    => ['nullable','integer','min:0'],
            'category' => ['nullable','string','max:100'],
            'is_active'=> ['sometimes','boolean'],
        ]);
    }
}
