<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyseQualiteRequest;
use App\Models\AnalyseQualite;
use App\Models\PointPrelevement;
use App\Services\DetectionContamination;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class AnalyseQualiteController extends Controller
{
    public function index(Request $request)
    {
        $filtrees = $this->filtrer($request);

        // Indicateurs calculés sur toutes les analyses filtrées (pas seulement la page affichée)
        $total = (clone $filtrees)->count();
        $conformes = (clone $filtrees)->where('conforme', true)->count();
        $indicateurs = [
            'total'        => $total,
            'conformes'    => $conformes,
            'nonConformes' => $total - $conformes,
            'taux'         => $total ? round($conformes / $total * 100, 1) : null,
        ];

        $analyses = $filtrees
            ->with('point')
            ->orderByDesc('date_prelevement')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.analyses.index', [
            'analyses'    => $analyses,
            'indicateurs' => $indicateurs,
            'points'      => $this->optionsPoints(),
        ]);
    }

    public function create(Request $request)
    {
        // Lien "Nouvelle analyse" depuis la fiche d'un point : le point est déjà choisi
        $analyse = new AnalyseQualite([
            'point_id'         => $request->integer('point') ?: null,
            'date_prelevement' => now(),
        ]);

        return view('admin.analyses.create', [
            'analyse' => $analyse,
            'points'  => $this->optionsPoints(actifsSeulement: true),
        ] + $this->donneesModeleIA());
    }

    // AnalyseQualiteRequest a déjà tout vérifié ; la conformité est calculée par le modèle
    public function store(AnalyseQualiteRequest $request)
    {
        $analyse = AnalyseQualite::create($request->validated());

        return redirect()->route('admin.analyses.show', $analyse)
            ->with('success', 'Analyse enregistrée : eau ' . mb_strtolower($analyse->libelleConformite()) . '.');
    }

    // Bulletin d'analyse
    public function show(AnalyseQualite $analyse)
    {
        $analyse->load('point.reseau.zone');

        // Ce que le modèle IA aurait prédit avec les seules mesures de terrain (à comparer au résultat du laboratoire)
        $predictionIA = DetectionContamination::predire(
            $analyse->only(['ph', 'chlore_residuel', 'turbidite', 'nitrates']),
            $analyse->point->type_point,
            $analyse->date_prelevement,
        );

        return view('admin.analyses.show', compact('analyse', 'predictionIA'));
    }

    public function edit(AnalyseQualite $analyse)
    {
        return view('admin.analyses.edit', [
            'analyse' => $analyse,
            // Points en service + le point actuel de l'analyse (même s'il a été mis hors service depuis)
            'points'  => $this->optionsPoints(actifsSeulement: true, inclure: $analyse->point_id),
        ] + $this->donneesModeleIA());
    }

    public function update(AnalyseQualiteRequest $request, AnalyseQualite $analyse)
    {
        $analyse->update($request->validated());

        return redirect()->route('admin.analyses.show', $analyse)
            ->with('success', 'Analyse modifiée : eau ' . mb_strtolower($analyse->libelleConformite()) . '.');
    }

    public function destroy(AnalyseQualite $analyse)
    {
        $analyse->delete();

        return redirect()->route('admin.analyses.index')->with('success', 'Analyse supprimée.');
    }

    // Bulletin d'analyse en PDF
    public function pdf(AnalyseQualite $analyse)
    {
        $analyse->load('point.reseau.zone');

        return Pdf::loadView('admin.analyses.pdf', ['analyse' => $analyse, 'genereLe' => now()])
            ->setPaper('a4')
            ->download('bulletin-analyse-' . $analyse->point->code . '-' . $analyse->date_prelevement->format('Y-m-d') . '.pdf');
    }

    // Export CSV des analyses filtrées (mêmes filtres que la liste), lisible directement par Excel
    public function export(Request $request)
    {
        $analyses = $this->filtrer($request)
            ->with('point.reseau.zone')
            ->orderByDesc('date_prelevement')
            ->orderByDesc('id');

        $nombre = fn ($valeur, int $decimales = 2) => number_format((float) $valeur, $decimales, ',', '');

        return response()->streamDownload(function () use ($analyses, $nombre) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");   // BOM : Excel reconnaît l'UTF-8 (accents)

            fputcsv($sortie, [
                'Date', 'Code point', 'Point', 'Commune', 'Réseau', 'pH', 'Chlore résiduel (mg/L)', 'Turbidité (NTU)',
                'Nitrates (mg/L)', 'Coliformes (UFC/100 mL)', 'Résultat', 'Paramètres hors norme', 'Laboratoire',
            ], ';');

            // lazy() : les analyses sont lues par paquets, même s'il y en a beaucoup
            foreach ($analyses->lazy(500) as $analyse) {
                fputcsv($sortie, [
                    $analyse->date_prelevement->format('d/m/Y'),
                    $analyse->point->code,
                    $analyse->point->nom,
                    $analyse->point->reseau->zone->commune,
                    $analyse->point->reseau->code,
                    $nombre($analyse->ph),
                    $nombre($analyse->chlore_residuel),
                    $nombre($analyse->turbidite),
                    $nombre($analyse->nitrates),
                    $analyse->bacteries_coliformes,
                    $analyse->libelleConformite(),
                    implode(' | ', $analyse->parametresHorsNorme()),
                    $analyse->laboratoire,
                ], ';');
            }

            fclose($sortie);
        }, 'analyses-qualite-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // Modèle IA de détection, envoyé au formulaire : l'arbre est parcouru en JavaScript pendant la saisie
    private function donneesModeleIA(): array
    {
        $modele = DetectionContamination::charger();

        return [
            'modeleIA' => $modele ? [
                'arbre'       => $modele['arbre']['racine'],
                'variables'   => $modele['variables'],
                'seuilAlerte' => DetectionContamination::SEUIL_ALERTE,
                'niveaux'     => DetectionContamination::NIVEAUX,
            ] : null,
            // Type de chaque point : variable d'entrée du modèle
            'typesPoints' => PointPrelevement::pluck('type_point', 'id')->all(),
        ];
    }

    // Filtres communs à la liste et à l'export : point, résultat, période
    private function filtrer(Request $request)
    {
        return AnalyseQualite::query()
            ->when($request->point, fn ($query, $point) => $query->where('point_id', $point))
            ->when(in_array($request->conforme, ['0', '1'], true), fn ($query) => $query->where('conforme', $request->conforme === '1'))
            ->when($request->du, fn ($query, $du) => $query->whereDate('date_prelevement', '>=', $du))
            ->when($request->au, fn ($query, $au) => $query->whereDate('date_prelevement', '<=', $au));
    }

    // Points pour les listes déroulantes : id => "PP-TN-012 — Fontaine Bab Saadoun"
    private function optionsPoints(bool $actifsSeulement = false, ?int $inclure = null): array
    {
        return PointPrelevement::query()
            ->when($actifsSeulement, fn ($q) => $q->where(fn ($q) => $q->where('actif', true)->orWhere('id', $inclure)))
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (PointPrelevement $p) => [$p->id => $p->libelle() . ($p->actif ? '' : ' (hors service)')])
            ->all();
    }
}
