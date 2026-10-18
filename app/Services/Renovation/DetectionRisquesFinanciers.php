<?php

namespace App\Services\Renovation;

use App\Models\Financement;
use App\Models\ProjetRenovation;
use Illuminate\Support\Facades\DB;

/*
 * Détection proactive des risques financiers : règles fixes, vérifiables et testables (aucune IA).
 *
 *   F1 SANS_FINANCEMENT         projet planifié ou en cours sans aucun financement
 *   F2 TERMINE_SANS_FINANCEMENT projet terminé sans financement enregistré (donnée manquante ou incohérente)
 *   F3 SURFINANCEMENT           financements > coût déclaré, ou > 150 % du coût estimé
 *   F4 MONTANT_INVALIDE         montant nul ou négatif en base (import, saisie directe…)
 *   F5 SOURCE_UNIQUE            chantier en cours qui dépend d'une seule source
 *   F6 CONCENTRATION            une source représente 60 % ou plus de tous les financements
 *   F7 COUT_MANQUANT            projets non terminés sans coût déclaré (couverture non calculable)
 *   F8 ORPHELIN                 financement rattaché à un projet inexistant
 *   F9 TERMINE_SOUS_FINANCE     projet terminé alors qu'une partie du coût déclaré n'est pas financée
 *   F10 MONTANT_ATYPIQUE        financement très au-dessus des autres (Q3 + 3 × écart interquartile, 8 financements minimum)
 */
class DetectionRisquesFinanciers
{
    public const SEUIL_CONCENTRATION = 60;      // %

    public const MARGE_ESTIMATION = 1.5;        // financé > 150 % de l'estimation = à vérifier

    public const MONTANTS_MIN = 8;              // F10 : nombre minimal de financements

    public const FACTEUR_TUKEY = 3;             // F10 : « valeur extrême » au sens de Tukey

    private const ORDRE = ['danger' => 0, 'warning' => 1, 'info' => 2];

    /**
     * Règles pures.
     *
     * @param list<array{id:int, titre:string, statut:string, cout:?float, cout_source:string, finance:float,
     *                   nb_financements:int, sources:array<string,float>, montants_invalides:int}> $projets
     * @param array{orphelins:int} $global
     * @return list<array{code:string, niveau:string, titre:string, message:string, projet_id:?int}>
     */
    public static function detecter(array $projets, array $global = []): array
    {
        $alertes = [];
        $sansCout = [];
        $parSource = [];

        foreach ($projets as $p) {
            foreach ($p['sources'] as $source => $montant) {
                $parSource[$source] = ($parSource[$source] ?? 0) + $montant;
            }

            if ($p['nb_financements'] === 0 && $p['statut'] !== 'termine') {
                $alertes[] = self::alerte('F1', $p['statut'] === 'en_cours' ? 'danger' : 'warning', 'Projet sans financement',
                    $p['statut'] === 'en_cours'
                        ? "Le chantier « {$p['titre']} » est en cours mais aucun financement n'est enregistré."
                        : "Le projet planifié « {$p['titre']} » n'a encore aucun financement.", $p['id']);
            }

            if ($p['nb_financements'] === 0 && $p['statut'] === 'termine') {
                $alertes[] = self::alerte('F2', 'danger', 'Projet terminé sans financement',
                    "« {$p['titre']} » est marqué terminé sans aucun financement enregistré : donnée manquante ou statut incohérent.", $p['id']);
            }

            if ($p['cout'] !== null && $p['cout'] > 0) {
                if ($p['cout_source'] === 'declare' && $p['finance'] > $p['cout'] + 0.005) {
                    $alertes[] = self::alerte('F3', 'danger', 'Financement supérieur au coût',
                        "« {$p['titre']} » : " . self::dt($p['finance']) . ' financés pour un coût déclaré de ' . self::dt($p['cout'])
                        . ' (excédent de ' . self::dt($p['finance'] - $p['cout']) . ').', $p['id']);
                } elseif ($p['cout_source'] !== 'declare' && $p['finance'] > $p['cout'] * self::MARGE_ESTIMATION) {
                    $alertes[] = self::alerte('F3', 'warning', 'Financement très supérieur à l\'estimation',
                        "« {$p['titre']} » : " . self::dt($p['finance']) . ' financés, plus de 150 % du coût estimé (' . self::dt($p['cout'])
                        . ') : saisissez le coût réel (devis) ou vérifiez les montants.', $p['id']);
                }

                if ($p['statut'] === 'termine' && $p['cout_source'] === 'declare' && $p['nb_financements'] > 0
                    && $p['finance'] < $p['cout'] - 0.005) {
                    $alertes[] = self::alerte('F9', 'warning', 'Projet terminé sous-financé',
                        "« {$p['titre']} » est terminé alors que " . self::dt($p['cout'] - $p['finance'])
                        . ' du coût déclaré ne sont couverts par aucun financement enregistré.', $p['id']);
                }
            }

            if ($p['montants_invalides'] > 0) {
                $alertes[] = self::alerte('F4', 'danger', 'Montant invalide',
                    "« {$p['titre']} » a {$p['montants_invalides']} financement(s) de montant nul ou négatif.", $p['id']);
            }

            if ($p['statut'] === 'en_cours' && $p['nb_financements'] > 0 && count($p['sources']) === 1) {
                $source = array_key_first($p['sources']);
                $alertes[] = self::alerte('F5', 'info', 'Dépendance à une seule source',
                    "Le chantier « {$p['titre']} » dépend uniquement de la source « " . Financement::libelleSourceDe($source)
                    . ' » : si elle fait défaut, il s\'arrête.', $p['id']);
            }

            if ($p['statut'] !== 'termine' && $p['cout_source'] !== 'declare') {
                $sansCout[] = $p['titre'];
            }
        }

        $total = array_sum($parSource);
        if ($total > 0) {
            arsort($parSource);
            $principale = array_key_first($parSource);
            $part = round(100 * $parSource[$principale] / $total, 1);
            if ($part >= self::SEUIL_CONCENTRATION && count($projets) > 1) {
                $hhi = (int) round(array_sum(array_map(fn ($m) => ($m / $total) ** 2, $parSource)) * 10000);
                $alertes[] = self::alerte('F6', 'warning', 'Sources de financement concentrées',
                    'La source « ' . Financement::libelleSourceDe($principale) . " » représente {$part} % de tous les financements "
                    . "(indice de concentration HHI : {$hhi} / 10 000).", null);
            }
        }

        if ($sansCout) {
            $alertes[] = self::alerte('F7', 'info', 'Coûts non déclarés',
                count($sansCout) . ' projet(s) non terminé(s) sans coût déclaré (devis) : leur taux de couverture ne peut pas être calculé de façon fiable'
                . ' (' . implode(', ', array_slice($sansCout, 0, 3)) . (count($sansCout) > 3 ? '…' : '') . ').', null);
        }

        foreach (self::montantsAtypiques($global['montants'] ?? []) as [$ligne, $seuil]) {
            $alertes[] = self::alerte('F10', 'warning', 'Montant atypique',
                'Financement de ' . self::dt($ligne['montant']) . ' (' . Financement::libelleSourceDe($ligne['source']) . ") sur « {$ligne['titre']} » : "
                . 'très au-dessus des autres financements (seuil statistique ' . self::dt($seuil) . '). Vérifiez la saisie.', $ligne['projet_id']);
        }

        if (($global['orphelins'] ?? 0) > 0) {
            $alertes[] = self::alerte('F8', 'danger', 'Financements orphelins',
                "{$global['orphelins']} financement(s) sont rattachés à un projet qui n'existe plus.", null);
        }

        usort($alertes, fn ($a, $b) => [self::ORDRE[$a['niveau']], $a['code'], $a['projet_id'] ?? 0]
            <=> [self::ORDRE[$b['niveau']], $b['code'], $b['projet_id'] ?? 0]);

        return $alertes;
    }

    /**
     * F10 — montants atypiques (méthode de Tukey) : au-delà de Q3 + 3 × écart interquartile.
     * Il faut au moins 8 financements pour que la règle ait un sens statistique.
     *
     * @param list<array{projet_id: int, titre: string, source: string, montant: float}> $montants
     * @return list<array{0: array, 1: float}>
     */
    public static function montantsAtypiques(array $montants): array
    {
        if (count($montants) < self::MONTANTS_MIN) {
            return [];
        }

        $valeurs = array_map(fn ($m) => (float) $m['montant'], $montants);
        sort($valeurs);
        $quantile = function (float $q) use ($valeurs) {
            $position = ($q * (count($valeurs) - 1));
            $bas = (int) floor($position);
            $haut = (int) ceil($position);

            return $valeurs[$bas] + ($valeurs[$haut] - $valeurs[$bas]) * ($position - $bas);
        };
        $q1 = $quantile(0.25);
        $q3 = $quantile(0.75);
        $seuil = $q3 + self::FACTEUR_TUKEY * ($q3 - $q1);

        if ($q3 - $q1 <= 0) {
            return [];
        }

        return array_values(array_map(fn ($m) => [$m, round($seuil, 2)],
            array_filter($montants, fn ($m) => (float) $m['montant'] > $seuil)));
    }

    /** Données réelles de la base, puis les règles. */
    public static function pourBase(): array
    {
        $montants = Financement::query()->join('projets_renovation', 'projets_renovation.id', '=', 'financements.projet_id')
            ->get(['financements.projet_id', 'projets_renovation.titre', 'financements.source', 'financements.montant'])
            ->map(fn ($f) => ['projet_id' => (int) $f->projet_id, 'titre' => $f->titre, 'source' => $f->source, 'montant' => (float) $f->montant])
            ->all();

        return self::detecter(self::projets(), ['orphelins' => self::orphelins(), 'montants' => $montants]);
    }

    public static function projets(): array
    {
        $projets = CandidatsWhatIf::requete()
            ->withCount(['financements', 'financements as montants_invalides' => fn ($q) => $q->where('montant', '<=', 0)])
            ->get();

        $sources = Financement::query()
            ->select('projet_id', 'source', DB::raw('SUM(montant) as total'))
            ->groupBy('projet_id', 'source')
            ->get()
            ->groupBy('projet_id');

        return $projets->map(function (ProjetRenovation $p) use ($sources) {
            $cout = ModeleCout::pourProjet($p);

            return [
                'id'                 => $p->id,
                'titre'              => $p->titre,
                'statut'             => $p->statut,
                'cout'               => $cout['montant'],
                'cout_source'        => $cout['source'],
                'finance'            => $p->totalFinance(),
                'nb_financements'    => (int) $p->financements_count,
                'sources'            => ($sources[$p->id] ?? collect())->mapWithKeys(fn ($l) => [$l->source => (float) $l->total])->all(),
                'montants_invalides' => (int) $p->montants_invalides,
            ];
        })->all();
    }

    public static function orphelins(): int
    {
        return Financement::query()->whereNotIn('projet_id', ProjetRenovation::query()->select('id'))->count();
    }

    public static function pourProjet(int $projetId, ?array $alertes = null): array
    {
        return array_values(array_filter($alertes ?? self::pourBase(), fn ($a) => $a['projet_id'] === $projetId));
    }

    private static function alerte(string $code, string $niveau, string $titre, string $message, ?int $projetId): array
    {
        return compact('code', 'niveau', 'titre', 'message') + ['projet_id' => $projetId];
    }

    private static function dt(float $montant): string
    {
        return number_format($montant, fmod($montant, 1) == 0 ? 0 : 2, ',', ' ') . ' DT';
    }
}
