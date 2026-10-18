<?php

namespace App\Services\Renovation;

use App\Models\ProjetRenovation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/*
 * Lecture des données RÉELLES pour le What If? Engine (lecture seule : aucune écriture en base).
 * Transforme chaque projet en « candidat » : tableau simple que le moteur sait traiter.
 */
class CandidatsWhatIf
{
    /** Projets avec tout ce qu'il faut pour le score et le coût, sans requêtes en boucle. */
    public static function requete(): Builder
    {
        return ProjetRenovation::query()
            ->with(['reseau' => fn ($q) => $q->with('zone')->withMax('historiques as etat_depuis', 'created_at')])
            ->withSum('financements', 'montant')
            ->orderBy('id');
    }

    /** @return list<array> candidats prêts pour MoteurWhatIf::comparer */
    public static function charger(): array
    {
        return self::depuisProjets(self::requete()->get());
    }

    public static function depuisProjets(Collection $projets): array
    {
        $territoires = EquiteTerritoriale::parGouvernorat();

        return $projets->map(function (ProjetRenovation $p) use ($territoires) {
            $priorite = ScorePriorite::pourProjet($p);
            $territoire = EquiteTerritoriale::gouvernoratDe($p->reseau?->zone?->commune);

            return [
                'territoire'            => $territoire,
                'territoire_population' => $territoires[$territoire]['population'] ?? 0,
                'territoire_finance'    => $territoires[$territoire]['finance'] ?? 0,
                'id'          => $p->id,
                'titre'       => $p->titre,
                'reseau'      => $p->reseau?->code ?? '—',
                'zone'        => $p->reseau?->zone?->libelle() ?? '—',
                'etat_reseau' => $p->reseau?->etat,
                'statut'      => $p->statut,
                'score'       => $priorite['score'],
                'cout'        => $priorite['cout']['montant'],
                'cout_source' => $priorite['cout']['source'],
                'finance'     => $p->totalFinance(),
            ];
        })->values()->all();
    }

    /**
     * Empreinte des données réelles utilisées par un calcul : si un coût, un statut ou un financement change,
     * l'empreinte change et le scénario enregistré ne peut plus être appliqué sans être recalculé.
     */
    public static function empreinte(array $candidats): string
    {
        $essentiel = array_map(fn ($c) => [
            (int) $c['id'], $c['statut'], $c['cout'] === null ? null : round((float) $c['cout'], 2),
            round((float) $c['finance'], 2), $c['etat_reseau'],
        ], $candidats);
        usort($essentiel, fn ($a, $b) => $a[0] <=> $b[0]);

        return hash('sha256', json_encode($essentiel));
    }
}
