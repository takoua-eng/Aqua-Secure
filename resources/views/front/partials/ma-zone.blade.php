{{-- "Ma zone" : le citoyen choisit la zone où il habite pour recevoir les avis de coupure --}}
@php $zonesChoix = \App\Http\Controllers\Front\CoupureController::optionsZones(); @endphp

@if (session('succes_zone'))
    <div class="alert alert-success py-2">{{ session('succes_zone') }}</div>
@endif

<form method="POST" action="{{ route('compte.zone') }}" class="row g-2 align-items-end">
    @csrf
    @method('PUT')
    <div class="col-sm">
        <label for="champ-zone_id" class="form-label">Zone où j'habite</label>
        <select name="zone_id" id="champ-zone_id" class="form-select {{ $errors->has('zone_id') ? 'is-invalid' : '' }}">
            <option value="">— Aucune (ne pas recevoir d'avis) —</option>
            @foreach ($zonesChoix as $commune => $zonesCommune)
                <optgroup label="{{ $commune }}">
                    @foreach ($zonesCommune as $id => $nom)
                        <option value="{{ $id }}" @selected((string) old('zone_id', auth()->user()->zone_id) === (string) $id)>{{ $nom }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <div class="invalid-feedback">{{ $errors->first('zone_id') }}</div>
    </div>
    <div class="col-sm-auto">
        <button type="submit" class="btn btn-eau">Enregistrer ma zone</button>
    </div>
</form>
<p class="small text-secondary mt-2 mb-0">Vous serez prévenu(e) par email des coupures d'eau programmées dans cette zone.</p>
