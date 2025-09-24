<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Faq;

class FaqController extends Controller
{
    public function index()
    {
        // kolom: is_active, order, question, answer, category
        $items = Faq::where('is_active', 1)
            ->orderBy('order')
            ->paginate(10);

        return view('pertanyaan_umum.index', compact('items'));
    }
}
