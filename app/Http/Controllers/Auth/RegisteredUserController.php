<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        abort_if(User::exists(), 403);

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(User::exists(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);
        $user = DB::transaction(function () use ($data) {
            $admin = Role::where('name', Role::ADMINISTRATOR)->lockForUpdate()->firstOrFail();
            abort_if(User::exists(), 403);
            $user = new User($data);
            $user->role()->associate($admin);
            $user->save();

            return $user;
        });
        event(new Registered($user));
        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
