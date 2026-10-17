<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $data = collect($request->validated())->except(['photo', 'supprimer_photo'])->all();

        // Photo facultative : enregistrée dans storage/app/public/photos
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('photos', 'public');
        }

        $user = User::create($data);

        // Compte créé par un administrateur : email considéré comme vérifié
        $user->markEmailAsVerified();

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur créé.');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(UserRequest $request, User $user)
    {
        $data = collect($request->validated())->except(['photo', 'supprimer_photo'])->all();

        // Un admin ne peut pas retirer son propre rôle admin
        if ($user->is(auth()->user()) && $data['role'] !== 'admin') {
            return back()->withInput()->withErrors(['role' => 'Vous ne pouvez pas retirer votre propre rôle administrateur.']);
        }

        // Nouvelle photo (l'ancienne est effacée) ou suppression demandée
        if ($request->hasFile('photo') || $request->boolean('supprimer_photo')) {
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }
            $data['photo'] = $request->hasFile('photo') ? $request->file('photo')->store('photos', 'public') : null;
        }

        // Mot de passe vide = on garde l'ancien
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur modifié.');
    }

    public function destroy(User $user)
    {
        if ($user->is(auth()->user())) {
            return back()->withErrors(['user' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }

        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur supprimé.');
    }
}