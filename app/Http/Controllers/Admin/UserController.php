<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->q, function ($query, $recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('nom', 'like', "%{$recherche}%")
                      ->orWhere('email', 'like', "%{$recherche}%");
                });
            })
            ->when($request->role, fn ($query, $role) => $query->where('role', $role))
            ->orderBy('nom')
            ->paginate(12)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create', ['user' => new User()]);
    }

    // UserRequest a déjà tout vérifié : on n'arrive ici que si les données sont valides
    public function store(UserRequest $request)
    {
        User::create($request->validated());

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur créé.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(UserRequest $request, User $user)
    {
        $data = $request->validated();

        // Mot de passe vide = on garde l'ancien
        if (empty($data['password'])) {
            unset($data['password']);
        }

        // Un admin ne peut pas retirer son propre rôle admin
        if ($user->is(auth()->user()) && $data['role'] !== 'admin') {
            return back()->withInput()->withErrors(['role' => 'Vous ne pouvez pas retirer votre propre rôle administrateur.']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur modifié.');
    }

    public function destroy(User $user)
    {
        if ($user->is(auth()->user())) {
            return back()->withErrors(['user' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur supprimé.');
    }
}