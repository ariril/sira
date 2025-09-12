<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\HalamanTentang;

class HalamanTentangController extends Controller
{
    public function show(string $tipe)
    {
        $page = HalamanTentang::where('tipe', $tipe)->firstOrFail();
        return view('pages.halaman_tentang', compact('page'));
    }
}
