<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Enums\AnnouncementCategory;
use App\Enums\AnnouncementLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementManageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $perPageOptions = [10, 12, 20, 30, 50];

        $data = $request->validate([
            'q'        => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(array_map(fn($c)=>$c->value, AnnouncementCategory::cases()))],
            'label'    => ['nullable', Rule::in(array_map(fn($c)=>$c->value, AnnouncementLabel::cases()))],
            'status'   => ['nullable', Rule::in(['draft','scheduled','published','expired'])],
            'per_page' => ['nullable', 'integer', 'in:' . implode(',', $perPageOptions)],
        ]);

        $q        = $data['q']        ?? null;
        $category = $data['category'] ?? null;
        $label    = $data['label']    ?? null;
        $status   = $data['status']   ?? null;
        $perPage  = (int)($data['per_page'] ?? 12);

        $now = now();

        $announcements = Announcement::query()
            ->when($q, function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('title', 'like', "%{$q}%")
                      ->orWhere('summary', 'like', "%{$q}%")
                      ->orWhere('content', 'like', "%{$q}%");
                });
            })
            ->when($category, fn($qb) => $qb->where('category', $category))
            ->when($label,    fn($qb) => $qb->where('label', $label))
            ->when($status === 'draft',      fn($qb) => $qb->whereNull('published_at'))
            ->when($status === 'scheduled',  fn($qb) => $qb->whereNotNull('published_at')->where('published_at', '>', $now))
            ->when($status === 'published',  fn($qb) => $qb->whereNotNull('published_at')
                                                    ->where('published_at', '<=', $now)
                                                    ->where(function ($w) use ($now) {
                                                        $w->whereNull('expired_at')->orWhere('expired_at', '>=', $now);
                                                    }))
            ->when($status === 'expired',    fn($qb) => $qb->whereNotNull('published_at')->where('expired_at', '<', $now))
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('super_admin.announcements.index', compact('announcements'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('super_admin.announcements.create', [
            'announcement' => new Announcement(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        // slug auto if empty
        if (empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['title']);
        }

        $data['author_id'] = Auth::id();
        $data['is_featured'] = $request->boolean('is_featured');

        // handle attachments
        $attachments = $this->storeAttachments($request);
        if (!empty($attachments)) {
            $data['attachments'] = $attachments;
        }

        Announcement::create($data);

        return redirect()->route('super_admin.announcements.index')
            ->with('status', 'Pengumuman berhasil dibuat.');
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
    public function edit(Announcement $announcement): View
    {
        return view('super_admin.announcements.edit', compact('announcement'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $this->validateData($request, $announcement->id);

        // slug: regenerate if empty, otherwise keep user-provided
        if (empty($data['slug'])) {
            $data['slug'] = $announcement->slug ?: $this->uniqueSlug($data['title']);
        }

        $data['is_featured'] = $request->boolean('is_featured');

        // attachments: append newly uploaded
        $existing = $announcement->attachments ?? [];
        $new = $this->storeAttachments($request);
        if (!empty($new)) {
            $data['attachments'] = array_values(array_merge($existing, $new));
        }

        $announcement->update($data);

        return redirect()->route('super_admin.announcements.index')
            ->with('status', 'Pengumuman berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('status', 'Pengumuman dihapus.');
    }

    // ===== Helpers =====

    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        $categoryValues = implode(',', array_map(fn($c)=>$c->value, AnnouncementCategory::cases()));
        $labelValues    = implode(',', array_map(fn($c)=>$c->value, AnnouncementLabel::cases()));

        return $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'slug'         => ['nullable', 'string', 'max:255', Rule::unique('announcements','slug')->ignore($ignoreId)],
            'summary'      => ['nullable', 'string'],
            // content must not be null to satisfy DB NOT NULL constraint
            'content'      => ['required', 'string'],
            'category'     => ['nullable', "in:$categoryValues"],
            'label'        => ['nullable', "in:$labelValues"],
            'is_featured'  => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'expired_at'   => ['nullable', 'date', 'after_or_equal:published_at'],
            'attachments'  => ['sometimes','array'],
            'attachments.*'=> ['file','max:20480'], // 20MB each
        ]);
    }

    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 2;
        while (Announcement::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }
        return $slug;
    }

    protected function storeAttachments(Request $request): array
    {
        $files = $request->file('attachments', []);
        $paths = [];
        foreach ($files as $file) {
            if ($file) {
                $paths[] = $file->store('announcements', 'public');
            }
        }
        return $paths;
    }
}
