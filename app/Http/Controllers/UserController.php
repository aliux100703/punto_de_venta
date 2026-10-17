<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::with('role')->orderBy('name')->paginate(20), 'roles' => Role::whereIn('name', [Role::ADMINISTRATOR, Role::SELLER])->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255', 'email' => 'required|email|lowercase|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role_id' => ['required', Rule::exists('roles', 'id')->whereIn('name', [Role::ADMINISTRATOR, Role::SELLER])],
        ]);
        DB::transaction(function () use ($data, $request) {
            Role::where('name', Role::ADMINISTRATOR)->lockForUpdate()->firstOrFail();
            abort_unless($request->user()->fresh()->isAdministrator(), 403);
            $user = new User(collect($data)->only(['name', 'email', 'password'])->all());
            $user->role()->associate(Role::findOrFail($data['role_id']));
            $user->save();
        });

        return to_route('users.index')->with('success', 'Usuario creado.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['role_id' => ['required', Rule::exists('roles', 'id')->whereIn('name', [Role::ADMINISTRATOR, Role::SELLER])]]);
        DB::transaction(function () use ($data, $user, $request) {
            // Lock the role first so demotions and account deletions share the same lock.
            $admin = Role::where('name', Role::ADMINISTRATOR)->lockForUpdate()->firstOrFail();
            abort_unless($request->user()->fresh()->isAdministrator(), 403);
            $target = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($target->role_id === $admin->id && (int) $data['role_id'] !== $admin->id && User::where('role_id', $admin->id)->count() <= 1) {
                throw ValidationException::withMessages(['role_id' => 'Debe quedar al menos un administrador. Crea o asigna otro antes de cambiar este rol.']);
            }
            $target->role()->associate(Role::findOrFail($data['role_id']));
            $target->save();
        });

        return to_route($request->user()->fresh()->isAdministrator() ? 'users.index' : 'dashboard')->with('success', 'Rol actualizado.');
    }
}
