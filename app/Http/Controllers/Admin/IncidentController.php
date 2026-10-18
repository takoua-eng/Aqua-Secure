<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\IncidentRequest;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use App\Models\ReseauEau;
use App\Models\Zone;
use App\Services\PrioriteIncident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

// Exploitation (binôme 2) : incidents techniques confirmés sur un réseau d'eau
class IncidentController extends Controller
{
    public const PAR_PAGE = 15;

    public const TRIS = [
        'date'     => 'Date de signalement',
        'gravite'  => 'Gravité',
        'priorite' => 'Priorité',
    ];

    public function index(Request $request)
    {
        $filtres = $this->filtres($request);
        $requete = $this->requeteFiltree($filtres)->with(['reseau.zone', 'interventions']);

        if ($filtres['tri'] === 'priorite') {
            // Le score est calculé en PHP (règles explicables) : on trie tous les incidents filtrés, puis on pagine
            $tous = $requete->get();
            $priorites = $tous->mapWithKeys(fn (Incident $i) => [$i->id => PrioriteIncident::calculer($i)]);
            $tries = $tous->sort(function (Incident $a, Incident $b) use ($priorites, $filtres) {
                $comparaison = $priorites[$a->id]['score'] <=> $priorites[$b->id]['score'];

                return $filtres['ordre'] === 'asc' ? $comparaison : -$comparaison;
            })->values();

            $page = LengthAwarePaginator::resolveCurrentPage();
            $incidents = new LengthAwarePaginator(
                $tries->forPage($page, self::PAR_PAGE)->values(), $tries->count(), self::PAR_PAGE, $page,
                ['path' => $request->url(), 'query' => $request->query()],
            );
        } else {
            $incidents = $requete->paginate(self::PAR_PAGE)->withQueryString();
            $priorites = $incidents->getCollection()->mapWithKeys(fn (Incident $i) => [$i->id => PrioriteIncident::calculer($i)]);
        }

        if ($request->wantsJson()) {
            return IncidentResource::collection($incidents);
        }

        return view('admin.incidents.index', [
            'incidents'   => $incidents,
            'priorites'   => $priorites,
            'filtres'     => $filtres,
            'reseaux'     => $this->optionsReseaux(),
            'zones'       => Zone::orderBy('nom')->get()->mapWithKeys(fn (Zone $z) => [$z->id => $z->libelle()])->all(),
            'indicateurs' => [
                'total'        => Incident::count(),
                'nonTraites'   => Incident::nonTraites()->count(),
                'enTraitement' => Incident::enTraitement()->count(),
                'critiques'    => Incident::critiquesSansIntervention()->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Incident::class);

        return view('admin.incidents.create', [
            'incident'         => new Incident([
                'reseau_id'        => $request->integer('reseau') ?: null,
                'date_signalement' => today(),
            ]),
            'reseaux'          => $this->optionsReseaux(),
            'reseauVerrouille' => false,
        ]);
    }

    // IncidentRequest a déjà tout vérifié : on n'arrive ici que si les données sont valides
    public function store(IncidentRequest $request)
    {
        Gate::authorize('create', Incident::class);

        $incident = Incident::create($request->validated());

        $redirection = redirect()->route('admin.incidents.show', $incident)
            ->with('success', "Incident n° {$incident->id} enregistré.");

        // Règle I6 : simple avertissement, l'incident est bien enregistré
        $doublons = Incident::doublonsPotentiels($incident->reseau_id, $incident->type, $incident->date_signalement, $incident->id)
            ->pluck('id');
        if ($doublons->isNotEmpty()) {
            $redirection->with('avertissement',
                'Un incident similaire non résolu existe déjà sur ce réseau (n° ' . $doublons->implode(', n° ')
                . ') : vérifiez qu\'il ne s\'agit pas d\'un doublon.');
        }

        return $redirection;
    }

    // Fiche : informations, statut calculé, score détaillé, interventions, incidents similaires
    public function show(Request $request, Incident $incident)
    {
        $incident->load([
            'reseau.zone',
            'interventions' => fn ($q) => $q->orderByDesc('date_intervention')->orderByDesc('id'),
        ]);

        if ($request->wantsJson()) {
            return new IncidentResource($incident);
        }

        return view('admin.incidents.show', [
            'incident' => $incident,
            'priorite' => PrioriteIncident::calculer($incident),
            'doublons' => Incident::doublonsPotentiels($incident->reseau_id, $incident->type, $incident->date_signalement, $incident->id)
                ->with('reseau')->orderByDesc('date_signalement')->get(),
        ]);
    }

    public function edit(Incident $incident)
    {
        Gate::authorize('update', $incident);

        return view('admin.incidents.edit', [
            'incident'         => $incident,
            'reseaux'          => $this->optionsReseaux(),
            'reseauVerrouille' => ! $incident->peutChangerDeReseau(),
        ]);
    }

    public function update(IncidentRequest $request, Incident $incident)
    {
        Gate::authorize('update', $incident);

        $incident->update($request->validated());

        return redirect()->route('admin.incidents.show', $incident)->with('success', "Incident n° {$incident->id} modifié.");
    }

    public function destroy(Incident $incident)
    {
        Gate::authorize('delete', $incident);

        // Règle I5 : on garde la trace des interventions
        if (! $incident->peutEtreSupprime()) {
            return back()->withErrors([
                'incident' => "L'incident n° {$incident->id} a des interventions : il ne peut pas être supprimé (traçabilité).",
            ]);
        }

        $incident->delete();

        return redirect()->route('admin.incidents.index')->with('success', "Incident n° {$incident->id} supprimé.");
    }

    // ---------- Outils ----------

    // Filtres de la liste : les valeurs inconnues sont ignorées (jamais d'erreur SQL)
    private function filtres(Request $request): array
    {
        // Texte simple uniquement (un paramètre envoyé sous forme de tableau est ignoré)
        $texte = fn (string $cle) => is_string($valeur = $request->query($cle)) ? trim($valeur) : '';
        $parmi = fn (string $cle, array $liste) => array_key_exists($texte($cle), $liste) ? $texte($cle) : null;
        $entier = fn (string $cle) => ctype_digit($texte($cle)) ? (int) $texte($cle) : null;
        $date = fn (string $cle) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $texte($cle)) && strtotime($texte($cle)) ? $texte($cle) : null;

        return [
            'q'         => mb_substr($texte('q'), 0, 100) ?: null,
            'type'      => $parmi('type', Incident::TYPES),
            'gravite'   => $parmi('gravite', Incident::GRAVITES),
            'statut'    => $parmi('statut', Incident::STATUTS),
            'reseau'    => $entier('reseau'),
            'zone'      => $entier('zone'),
            'du'        => $date('du'),
            'au'        => $date('au'),
            'critiques' => $request->boolean('critiques'),
            'tri'       => $parmi('tri', self::TRIS) ?? 'date',
            'ordre'     => $texte('ordre') === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function requeteFiltree(array $f): Builder
    {
        return Incident::query()
            // Recherche : n° d'incident, code du réseau, nom de la zone ou commune
            ->when($f['q'], function (Builder $query, string $recherche) {
                $query->where(function (Builder $q) use ($recherche) {
                    $q->whereHas('reseau', fn ($r) => $r->where('code', 'like', "%{$recherche}%"))
                      ->orWhereHas('reseau.zone', fn ($z) => $z->where('nom', 'like', "%{$recherche}%")
                          ->orWhere('commune', 'like', "%{$recherche}%"));
                    if (ctype_digit(ltrim($recherche, '#'))) {
                        $q->orWhere('incidents.id', (int) ltrim($recherche, '#'));
                    }
                });
            })
            ->when($f['type'], fn ($query, $type) => $query->where('type', $type))
            ->when($f['gravite'], fn ($query, $gravite) => $query->where('gravite', $gravite))
            ->when($f['reseau'], fn ($query, $reseau) => $query->where('reseau_id', $reseau))
            ->when($f['zone'], fn ($query, $zone) => $query->whereHas('reseau', fn ($r) => $r->where('zone_id', $zone)))
            ->when($f['statut'], fn ($query, $statut) => $query->avecStatut($statut))
            ->when($f['du'], fn ($query, $du) => $query->whereDate('date_signalement', '>=', $du))
            ->when($f['au'], fn ($query, $au) => $query->whereDate('date_signalement', '<=', $au))
            ->when($f['critiques'], fn ($query) => $query->critiquesSansIntervention())
            ->when($f['tri'] === 'gravite', fn ($query) => $query->ordreGravite($f['ordre'])->orderByDesc('date_signalement'))
            ->when($f['tri'] === 'date', fn ($query) => $query->orderBy('date_signalement', $f['ordre']))
            ->orderByDesc('incidents.id');
    }

    // Réseaux pour les listes déroulantes : id => "CAN-TUN-001 — Canalisation (Centre-ville)"
    private function optionsReseaux(): array
    {
        return ReseauEau::with('zone')->orderBy('code')->get()
            ->mapWithKeys(fn (ReseauEau $r) => [$r->id => "{$r->code} — {$r->libelleType()} ({$r->zone->nom})"])
            ->all();
    }
}
