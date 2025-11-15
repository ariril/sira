<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Announcement;

class AnnouncementController extends Controller
{
    public function index()
    {
        $items = Announcement::query()
            ->when(request('kategori'), function ($q, $cat) {
                return $q->where('category', $cat);
            })
            ->when(request('label'), function ($q, $lbl) {
                return $q->where('label', $lbl);
            })
            ->when(request('q'), function ($q, $term) {
                return $q->where(function ($qq) use ($term) {
                    $like = "%" . $term . "%";
                    $qq->where('title', 'like', $like)
                       ->orWhere('content', 'like', $like);
                });
            })
            ->orderByDesc('published_at')
            ->paginate(10)
            ->withQueryString();

        return view('pengumuman.index', compact('items'));
    }

    public function show(string $slug)
    {
        $item = Announcement::where('slug', $slug)->firstOrFail();

        return view('pengumuman.show', compact('item'));
    }
}
