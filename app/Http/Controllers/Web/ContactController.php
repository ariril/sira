<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Contracts\View\View;

class ContactController extends Controller
{
    public function index(): View
    {
        $site = SiteSetting::query()->first();
        return view('pages.contact', compact('site'));
    }
}
