<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Announcement;

class AnnouncementController extends Controller
{
    public function index()
    {
        $items = Announcement::orderByDesc('dipublikasikan_pada')
            ->paginate(10);

        return view('pengumuman.index', compact('items'));
    }

    public function show(string $slug)
    {
        $item = Announcement::where('slug', $slug)->firstOrFail();

        return view('pengumuman.show', compact('item'));
    }
}
