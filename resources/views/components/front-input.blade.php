{{--
    Champ du site public (Bootstrap 5) avec icône et états de validation.
--}}
@props([
    'name',
    'label' => null,
    'type' => 'text',
    'icon' => null,
    'value' => null,
    'required' => false,
    'validMessage' => 'Parfait !',
])

@php
    $id = $attributes->get('id', 'champ-' . $name);
    $erreur = $errors->first($name);
    $valeur = $type === 'password' ? null : old($name, $value);

    $etat = '';
    if ($erreur) {
        $etat = 'is-invalid';
    } elseif ($errors->any() && $type !== 'password' && filled(old($name))) {
        $etat = 'is-valid';
    }
@endphp

<div class="mb-3">
    @if ($label)
        <label class="form-label" for="{{ $id }}">
            {{ $label }} @if ($required)<span class="text-danger">*</span>@endif
        </label>
    @endif

    <div class="input-group">
        @if ($icon)
            <span class="input-group-text"><i class="bi bi-{{ $icon }}"></i></span>
        @endif
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $valeur }}"
               @required($required)
               {{ $attributes->except('id')->merge(['class' => 'form-control ' . $etat]) }}>
    </div>

    <div class="invalid-feedback {{ $erreur ? 'd-block' : '' }}" data-feedback-for="{{ $name }}">{{ $erreur }}</div>
    <div class="valid-feedback {{ $etat === 'is-valid' ? 'd-block' : '' }}" data-valid-for="{{ $name }}">{{ $validMessage }}</div>
</div>