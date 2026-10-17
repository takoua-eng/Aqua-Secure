{{--
    Avatar Tabler d'un utilisateur : sa photo de profil, sinon ses initiales colorées selon le rôle.
    Exemple : x-avatar avec :user="$user" taille="xl" class="mb-3"  (taille : sm, md, lg, xl ; vide = normale)
--}}
@props(['user', 'taille' => null])

@php
    $classes = 'avatar rounded-circle' . ($taille ? " avatar-{$taille}" : '');
@endphp

@if ($user->photo)
    <span {{ $attributes->merge(['class' => $classes]) }} style="background-image: url('{{ $user->photoUrl() }}')"
          title="{{ $user->nom }}"></span>
@else
    <span {{ $attributes->merge(['class' => $classes . ' bg-' . $user->couleurRole() . '-lt']) }}
          title="{{ $user->nom }}">{{ $user->initiales() }}</span>
@endif