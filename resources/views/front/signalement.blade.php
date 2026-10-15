@extends('layouts.front')

@section('title', 'Signaler un incident')

@section('content')
<section class="section section-clair">
    <div class="container" style="max-width: 820px">
        <h1 class="fw-bold mb-2">Signaler un incident</h1>
        <p class="text-secondary mb-4">Cliquez sur la carte pour indiquer l'endroit exact du problème.</p>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="card border-0 shadow-sm p-4">
            <form method="POST" action="{{ route('signalement.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Type d'incident *</label>
                        <select name="type" class="form-select" required>
                            <option value="">— Choisir —</option>
                            <option value="fuite" @selected(old('type') === 'fuite')>Fuite</option>
                            <option value="contamination" @selected(old('type') === 'contamination')>Contamination</option>
                            <option value="coupure" @selected(old('type') === 'coupure')>Coupure (sécheresse)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Localité *</label>
                        <input type="text" name="localite" class="form-control" value="{{ old('localite') }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control">{{ old('description') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Photo (facultatif)</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Votre email (pour le suivi)</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Emplacement *</label>
                        <div id="map" class="rounded-3 border" style="height: 340px"></div>
                        <div class="form-text" id="position-texte">Aucun emplacement choisi.</div>
                        <input type="hidden" name="latitude" id="lat" value="{{ old('latitude') }}">
                        <input type="hidden" name="longitude" id="lng" value="{{ old('longitude') }}">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-eau btn-lg px-4">
                            <i class="bi bi-send"></i> Envoyer le signalement
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const map = L.map('map').setView([36.8065, 10.1815], 12);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);

        let marker;
        function placer(latlng) {
            if (marker) marker.setLatLng(latlng); else marker = L.marker(latlng).addTo(map);
            document.getElementById('lat').value = latlng.lat.toFixed(6);
            document.getElementById('lng').value = latlng.lng.toFixed(6);
            document.getElementById('position-texte').textContent =
                `Position : ${latlng.lat.toFixed(5)}, ${latlng.lng.toFixed(5)}`;
        }
        map.on('click', e => placer(e.latlng));

        // Réafficher la position après une erreur de validation
        const oldLat = document.getElementById('lat').value, oldLng = document.getElementById('lng').value;
        if (oldLat && oldLng) placer(L.latLng(oldLat, oldLng));
    </script>
@endpush
