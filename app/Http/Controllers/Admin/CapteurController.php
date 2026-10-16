<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CapteurRequest;
use App\Models\Capteur;
use App\Models\Releve;
use App\Models\ReseauEau;
use App\Services\AnalyseCapteur;
use App\Services\DiagnosticCapteurIA;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use App\Services\AnalyseGlobaleCapteursIA;
use App\Services\GraphiqueSvg;
use Barryvdh\DomPDF\Facade\Pdf; 


class CapteurController extends Controller
{
    public function index(Request $request)
    {
        $capteurs = Capteur::query()
            ->with(['reseau.zone', 'dernierReleve'])
            // Nombre de relevés hors seuil sur les 7 derniers jours
            ->withCount(['releves as alertes_count' => fn ($q) => $q
                ->where('hors_seuil', true)
                ->where('date_releve', '>=', now()->subDays(7))])
            ->when($request->q, fn ($query, $q) => $query->where(fn ($sous) => $sous
                ->where('code', 'like', "%{$q}%")
                ->orWhere('modele', 'like', "%{$q}%")))
            ->when($request->reseau, fn ($query, $reseau) => $query->where('reseau_id', $reseau))
            ->when($request->type, fn ($query, $type) => $query->where('type_mesure', $type))
            ->when($request->statut, fn ($query, $statut) => $query->where('statut', $statut))
            // Filtre "en alerte" : au moins un relevé hors seuil sur 24 h
            ->when($request->boolean('alerte'), fn ($query) => $query->whereHas('releves', fn ($q) => $q
                ->where('hors_seuil', true)
                ->where('date_releve', '>=', now()->subDay())))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        // Chiffres des 4 cartes en haut de page
        $stats = [
            'total'   => Capteur::count(),
            'actifs'  => Capteur::where('statut', 'actif')->count(),
            'panne'   => Capteur::where('statut', '!=', 'actif')->count(),
            'alertes' => Releve::where('hors_seuil', true)->where('date_releve', '>=', now()->subDay())->count(),
        ];

        return view('admin.capteurs.index', [
            'capteurs' => $capteurs,
            'stats'    => $stats,
            'reseaux'  => $this->optionsReseaux(),
        ]);
    }

    // Fiche d'un capteur : informations, graphique, analyse intelligente et relevés
    public function show(Capteur $capteur)
    {
        $capteur->load('reseau.zone');

        // 50 derniers relevés, remis dans l'ordre chronologique
        $derniers = $capteur->releves()->orderByDesc('date_releve')->limit(50)->get()->reverse()->values();

        // IA locale : anomalies (score z robuste) et prévision (régression linéaire)
        $anomalies = AnalyseCapteur::anomalies($derniers);
        $prevision = AnalyseCapteur::prevision($capteur, $derniers);

        $graphique = $derniers->map(fn (Releve $r) => [
            'x' => $r->date_releve->getTimestampMs(),
            'y' => round($r->valeur, 2),
            'anomalie' => isset($anomalies[$r->id]),
        ]);

        $resume = [
            'nombre'     => $derniers->count(),
            'min'        => $derniers->min('valeur'),
            'max'        => $derniers->max('valeur'),
            'moyenne'    => $derniers->avg('valeur'),
            'hors_seuil' => $derniers->where('hors_seuil', true)->count(),
        ];

        $releves = $capteur->releves()->orderByDesc('date_releve')->paginate(10);

        return view('admin.capteurs.show', compact('capteur', 'graphique', 'resume', 'releves', 'anomalies', 'prevision'));
    }

    // Diagnostic rédigé par l'IA (Claude), demandé en arrière-plan depuis la fiche du capteur
    public function diagnostic(Capteur $capteur): JsonResponse
    {
        set_time_limit(120); // la réponse de l'IA peut prendre plusieurs dizaines de secondes

        try {
            return response()->json(DiagnosticCapteurIA::diagnostiquer($capteur));
        } catch (RuntimeException $e) {
            return response()->json(['erreur' => $e->getMessage()], 503);
        }
    }

    public function create(Request $request)
    {
        return view('admin.capteurs.create', [
            // ?reseau=ID permet de pré-remplir le réseau
            'capteur' => new Capteur([
                'statut'            => 'actif',
                'reseau_id'         => $request->integer('reseau') ?: null,
                'date_installation' => now(),
            ]),
            'reseaux' => $this->optionsReseaux(),
        ]);
    }

        // Analyse globale de tous les capteurs, regroupés par zone (bouton sur la liste des capteurs)
    public function analyseGlobale(): JsonResponse
    {
        set_time_limit(120);

        try {
            return response()->json(AnalyseGlobaleCapteursIA::analyser());
        } catch (RuntimeException $e) {
            return response()->json(['erreur' => $e->getMessage()], 503);
        }
    }

    // Rapport PDF d'un capteur : fiche, statistiques, graphique, prévision, anomalies et diagnostic IA
    public function rapportPdf(Capteur $capteur)
    {
        set_time_limit(120);
        $capteur->load('reseau.zone');

        $derniers = $capteur->releves()->orderByDesc('date_releve')->limit(50)->get()->reverse()->values();
        $anomalies = AnalyseCapteur::anomalies($derniers);
        $prevision = AnalyseCapteur::prevision($capteur, $derniers);

        // Diagnostic IA : s'il échoue (pas d'Internet…), le rapport est quand même produit
        try {
            $diagnostic = DiagnosticCapteurIA::diagnostiquer($capteur);
        } catch (RuntimeException $e) {
            $diagnostic = ['erreur' => $e->getMessage()];
        }

        $svg = GraphiqueSvg::pourCapteur($capteur, $derniers, $anomalies, $prevision);

        return Pdf::loadView('admin.capteurs.pdf', [
            'capteur'    => $capteur,
            'derniers'   => $derniers,
            'anomalies'  => $anomalies,
            'prevision'  => $prevision,
            'diagnostic' => $diagnostic,
            'graphique'  => $svg ? 'data:image/svg+xml;base64,' . base64_encode($svg) : null,
            'resume'     => [
                'nombre'     => $derniers->count(),
                'min'        => $derniers->min('valeur'),
                'max'        => $derniers->max('valeur'),
                'moyenne'    => $derniers->avg('valeur'),
                'hors_seuil' => $derniers->where('hors_seuil', true)->count(),
            ],
            'genereLe'   => now(),
        ])->setPaper('a4')->download("rapport-{$capteur->code}-" . now()->format('Y-m-d') . '.pdf');
    }

    // CapteurRequest a déjà tout vérifié : on n'arrive ici que si les données sont valides
    public function store(CapteurRequest $request)
    {
        $capteur = Capteur::create($request->validated());

        return redirect()->route('admin.capteurs.show', $capteur)
            ->with('success', "Capteur {$capteur->code} créé.");
    }

    public function edit(Capteur $capteur)
    {
        return view('admin.capteurs.edit', [
            'capteur' => $capteur,
            'reseaux' => $this->optionsReseaux(),
        ]);
    }

    // Si les seuils changent, le modèle Capteur recalcule les relevés hors seuil
    public function update(CapteurRequest $request, Capteur $capteur)
    {
        $capteur->update($request->validated());

        return redirect()->route('admin.capteurs.show', $capteur)
            ->with('success', "Capteur {$capteur->code} modifié.");
    }

    // Les relevés du capteur sont supprimés avec lui (cascade en base)
    public function destroy(Capteur $capteur)
    {
        $capteur->delete();

        return redirect()->route('admin.capteurs.index')
            ->with('success', "Capteur {$capteur->code} supprimé.");
    }

    // Réseaux pour les listes déroulantes : id => "CAN-TUN-001 — Canalisation (Centre-ville)"
    private function optionsReseaux(): array
    {
        return ReseauEau::with('zone')->orderBy('code')->get()
            ->mapWithKeys(fn (ReseauEau $r) => [
                $r->id => "{$r->code} — {$r->libelleType()} ({$r->zone->nom})",
            ])
            ->all();
    }
}