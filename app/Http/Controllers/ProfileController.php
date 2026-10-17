<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        DB::transaction(function () use ($request) {
            $admin = Role::where('name', Role::ADMINISTRATOR)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ($user->role_id === $admin->id && User::where('role_id', $admin->id)->count() <= 1) {
                throw ValidationException::withMessages(['password' => 'No puedes eliminar al último administrador.'])->errorBag('userDeletion');
            }
            if ($user->saleReturns()->exists() || $user->sales()->exists() || $user->inventoryMovements()->exists() || $user->quotes()->exists() || $user->cashRegisters()->exists() || $user->cashMovements()->exists() || $user->expenses()->exists()) {
                throw ValidationException::withMessages(['password' => 'Tu cuenta tiene operaciones registradas y debe conservarse para mantener el historial.'])->errorBag('userDeletion');
            }
            $user->delete();
        });
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
