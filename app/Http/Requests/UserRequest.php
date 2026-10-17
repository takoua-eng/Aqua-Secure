<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    // Seul un administrateur peut créer ou modifier un utilisateur
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        // En modification, l'utilisateur est dans l'URL : /admin/users/{user}
        $user = $this->route('user');

        return [
            'nom'      => ['required', 'string', 'min:3', 'max:100', "regex:/^[\pL\s'\-]+$/u"],
            'email'    => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role'     => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => [
                $user ? 'nullable' : 'required',      // obligatoire seulement à la création
                'string',
                'min:8',
                'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/',  // au moins une lettre et un chiffre
                'confirmed',
            ],
                        // Photo de profil facultative (JPG, PNG ou WEBP, 2 Mo maximum)
            'photo'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=100,min_height=100'],
            'supprimer_photo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'       => 'Le nom est obligatoire.',
            'nom.min'            => 'Le nom doit contenir au moins 3 caractères.',
            'nom.max'            => 'Le nom ne peut pas dépasser 100 caractères.',
            'nom.regex'          => 'Le nom ne peut contenir que des lettres, des espaces, des tirets et des apostrophes.',

            'email.required'     => "L'email est obligatoire.",
            'email.email'        => "L'adresse email n'est pas valide.",
            'email.max'          => "L'email ne peut pas dépasser 255 caractères.",
            'email.unique'       => 'Cet email est déjà utilisé par un autre compte.',

            'role.required'      => 'Le rôle est obligatoire.',
            'role.in'            => "Le rôle choisi n'existe pas.",

            'password.required'  => 'Le mot de passe est obligatoire.',
            'password.min'       => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.regex'     => 'Le mot de passe doit contenir au moins une lettre et un chiffre.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
            
            'photo.image'        => 'Le fichier doit être une image.',
            'photo.mimes'        => 'La photo doit être au format JPG, PNG ou WEBP.',
            'photo.max'          => 'La photo ne doit pas dépasser 2 Mo.',
            'photo.dimensions'   => 'La photo doit mesurer au moins 100 × 100 pixels.',
        ];
    }
}