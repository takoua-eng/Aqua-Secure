<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Services\PrioriteIncident;
use App\Services\StatistiquesIncidents;
use DateTime;
use Illuminate\Http\Request;

// Exploitation (binôme 2) : priorisation explicable et statistiques des incidents
class IncidentAnalyseController extends Controller
{
    public function priorites(Request $request)
    {
        $classement = PrioriteIncident::classement();
        $niveau = collect(PrioriteIncident::NIVEAUX)->pluck('cle')->contains($request->query('niveau'))
            ? $request->query('niveau') : null;

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $classement->map(fn ($ligne) => [
                    'incident_id' => $ligne['incident']->id,
                    'reseau'      => $ligne['incident']->reseau->code,
                    'type'        => $ligne['incident']->type,
                    'gravite'     => $ligne['incident']->gravite,
                    'priorite'    => $ligne['priorite'],
                ])->values(),
            ]);
        }

        return view('admin.incidents.priorites', [
            'classement' => $niveau
                ? $classement->filter(fn ($ligne) => $ligne['priorite']['niveau']['cle'] === $niveau)->values()
                : $classement,
            'niveau'     => $niveau,
            'parNiveau'  => collect(PrioriteIncident::NIVEAUX)->mapWithKeys(fn ($n) => [
                $n['cle'] => $classement->filter(fn ($ligne) => $ligne['priorite']['niveau']['cle'] === $n['cle'])->count(),
            ]),
            'critiques'  => Incident::critiquesSansIntervention()->with('reseau.zone')->orderBy('date_signalement')->get(),
            'recurrents' => PrioriteIncident::reseauxRecurrents(),
        ]);
    }

    public function statistiques(Request $request)
    {
        [$filtres, $erreur] = $this->filtresStatistiques($request);

        return view('admin.incidents.statistiques', [
            'stats'         => StatistiquesIncidents::calculer($filtres),
            'filtres'       => $filtres,
            'filtreActif'   => StatistiquesIncidents::filtreActif($filtres),
            'erreurFiltres' => $erreur,
        ]);
    }

    // Mêmes chiffres en JSON (API de statistiques, protégée par la session et le rôle), mêmes filtres
    public function donnees(Request $request)
    {
        [$filtres, $erreur] = $this->filtresStatistiques($request);

        if ($erreur) {
            return response()->json(['message' => $erreur], 422);
        }

        return response()->json(StatistiquesIncidents::calculer($filtres));
    }

    /*
     * Filtres du / au (AAAA-MM-JJ, date réelle) et type. Les valeurs invalides sont ignorées ;
     * une date de fin antérieure à la date de début annule la période et renvoie un message.
     */
    private function filtresStatistiques(Request $request): array
    {
        $texte = fn (string $cle) => is_string($valeur = $request->query($cle)) ? trim($valeur) : '';
        $date = function (string $cle) use ($texte) {
            $valeur = $texte($cle);
            $objet = DateTime::createFromFormat('!Y-m-d', $valeur);

            return $objet && $objet->format('Y-m-d') === $valeur ? $valeur : null;
        };

        $filtres = [
            'du'   => $date('du'),
            'au'   => $date('au'),
            'type' => array_key_exists($texte('type'), Incident::TYPES) ? $texte('type') : null,
        ];

        if ($filtres['du'] && $filtres['au'] && $filtres['au'] < $filtres['du']) {
            return [
                ['du' => null, 'au' => null, 'type' => $filtres['type']],
                'La date de fin précède la date de début : la période a été ignorée.',
            ];
        }

        return [$filtres, null];
    }
}
