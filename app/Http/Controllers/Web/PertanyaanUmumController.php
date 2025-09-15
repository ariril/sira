<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Faq;

class PertanyaanUmumController extends Controller
{
    public function index()
    {
        $items = Faq::where('aktif', 1)
            ->orderBy('urutan')
            ->paginate(10);

        return view('pertanyaan_umum.index', compact('items'));
    }
}
