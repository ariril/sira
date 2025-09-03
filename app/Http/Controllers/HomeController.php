<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    // Arahkan user ke dashboard sesuai role
    public function index(): RedirectResponse
    {
        $role = Auth::user()->role;

        return match ($role) {
            'super_admin'  => redirect()->route('super_admin.dashboard'),
            'kepala_unit'  => redirect()->route('kepala_unit.dashboard'),
            'administrasi' => redirect()->route('administrasi.dashboard'),
            default        => redirect()->route('pegawai_medis.dashboard'),
        };
    }
}
