<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AboutPage;

class HalamanTentangController extends Controller
{
    public function show(string $tipe)
    {
        $page = AboutPage::where('tipe', $tipe)->firstOrFail();
        return view('pages.halaman_tentang', compact('page'));
    }
}
