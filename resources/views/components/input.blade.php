{{--
    Champ Tabler avec icône et états de validation (rouge / vert).
    Exemple :
    <x-input name="email" type="email" label="Email" icon="mail" required
             data-msg-required="L'email est obligatoire." />
--}}
@props([
    'name',
    'label' => null,
    'type' => 'text',
    'icon' => null,
    'value' => null,
    'required' => false,
    'hint' => null,
    'validMessage' => 'Parfait !',
])

@php
    $id = $attributes->get('id', 'champ-' . $name);
    $erreur = $errors->first($name);

    // Un mot de passe n'est jamais réaffiché
    $valeur = $type === 'password' ? null : old($name, $value);

    // Après un envoi refusé par Laravel : rouge si erreur, vert si le champ était correct
    $etat = '';
    if ($erreur) {
        $etat = 'is-invalid';
    } elseif ($errors->any() && $type !== 'password' && filled(old($name))) {
        $etat = 'is-valid';
    }
@endphp

<div class="mb-3">
    @if ($label)
        <label class="form-label {{ $required ? 'required' : '' }}" for="{{ $id }}">{{ $label }}</label>
    @endif

    <div class="{{ $icon ? 'input-icon' : '' }}">
        @if ($icon)
            <span class="input-icon-addon"><i class="ti ti-{{ $icon }}"></i></span>
        @endif

        <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $valeur }}"
               @required($required)
               {{ $attributes->except('id')->merge(['class' => 'form-control ' . $etat]) }}>
    </div>

    {{-- Messages affichés sous le champ (remplis par Laravel ou par validation.js) --}}
    <div class="invalid-feedback {{ $erreur ? 'd-block' : '' }}" data-feedback-for="{{ $name }}">{{ $erreur }}</div>
    <div class="valid-feedback {{ $etat === 'is-valid' ? 'd-block' : '' }}" data-valid-for="{{ $name }}">{{ $validMessage }}</div>

    @if ($hint)
        <small class="form-hint">{{ $hint }}</small>
    @endif
</div>