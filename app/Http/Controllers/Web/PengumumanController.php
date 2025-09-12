<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;

class PengumumanController extends Controller
{
    public function index()
    {
        $items = Pengumuman::orderByDesc('dipublikasikan_pada')
            ->paginate(10);

        return view('pengumuman.index', compact('items'));
    }

    public function show(string $slug)
    {
        $item = Pengumuman::where('slug', $slug)->firstOrFail();

        return view('pengumuman.show', compact('item'));
    }
}
