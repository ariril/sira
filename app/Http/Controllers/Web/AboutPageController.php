<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AboutPage;

class AboutPageController extends Controller
{
    public function show(string $type)
    {
        // kolom: type (visi|misi|struktur|profil_rs|tugas_fungsi)
        $page = AboutPage::where('type', $type)->firstOrFail();
        return view('pages.halaman_tentang', compact('page'));
    }
}
