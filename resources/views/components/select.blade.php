{{--
    Liste déroulante Tabler avec icône et états de validation.
    Exemple :
    <x-select name="role" label="Rôle" icon="shield" :options="\App\Models\User::ROLES" required />
--}}
@props([
    'name',
    'label' => null,
    'icon' => null,
    'options' => [],
    'value' => null,
    'required' => false,
    'placeholder' => '— Choisir —',
    'validMessage' => 'Parfait !',
])

@php
    $id = $attributes->get('id', 'champ-' . $name);
    $erreur = $errors->first($name);
    $choisi = (string) old($name, $value);

    $etat = '';
    if ($erreur) {
        $etat = 'is-invalid';
    } elseif ($errors->any() && filled(old($name))) {
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

        <select name="{{ $name }}" id="{{ $id }}" @required($required)
                {{ $attributes->except('id')->merge(['class' => 'form-select ' . $etat]) }}
                @if ($icon) style="padding-left: 2.5rem" @endif>
            <option value="">{{ $placeholder }}</option>
            @foreach ($options as $valeur => $libelle)
                <option value="{{ $valeur }}" @selected($choisi === (string) $valeur)>{{ $libelle }}</option>
            @endforeach
        </select>
    </div>

    <div class="invalid-feedback {{ $erreur ? 'd-block' : '' }}" data-feedback-for="{{ $name }}">{{ $erreur }}</div>
    <div class="valid-feedback {{ $etat === 'is-valid' ? 'd-block' : '' }}" data-valid-for="{{ $name }}">{{ $validMessage }}</div>
</div>