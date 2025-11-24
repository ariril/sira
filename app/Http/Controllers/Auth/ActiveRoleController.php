<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;

class ActiveRoleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'role' => ['required','string','exists:roles,slug'],
        ]);

        $user = $request->user();
        $slug = $request->string('role');

        // Ensure user actually owns the role
        if (!$user->hasRole($slug)) {
            abort(403);
        }

        session(['active_role' => (string)$slug]);
        $user->forceFill(['last_role' => (string)$slug])->save();

        return redirect()->route('dashboard');
    }
}
