<?php

namespace App\Services\Renovation;

/*
 * Garde-fous appliqués à TOUTE réponse de l'IA avant affichage (PHP pur, testable sans réseau).
 *   1. Structure : clés attendues, types, longueurs et nombre d'éléments limités.
 *   2. Stratégies : uniquement celles calculées par le moteur.
 *   3. Projets cités : uniquement des identifiants présents dans les données envoyées.
 *   4. Chiffres : tout nombre cité doit exister dans les données calculées (pas de chiffre inventé).
 * Une réponse qui échoue est écartée : l'application affiche alors l'analyse du mode secours (règles).
 */
class ValidationReponseIA
{
    public const LONGUEUR_MAX = 1200;

    public const ELEMENTS_MAX = 8;

    // Nombres « petits » acceptés sans preuve (rangs, lettres de stratégie, comptes, numéros dans les codes réseau)
    public const PETIT_NOMBRE = 20;

    /** Réponse à l'analyse des scénarios. */
    public static function scenarios(mixed $reponse, array $strategies, array $idsProjets, array $nombres): array
    {
        $r = self::objet($reponse, ['synthese', 'recommandation', 'strategies', 'donnees_manquantes', 'ameliorations', 'projets_cites']);

        $propre = [
            'synthese'           => self::texte($r['synthese'], 'synthese'),
            'recommandation'     => self::objet($r['recommandation'], ['strategie', 'justification'], 'recommandation'),
            'strategies'         => [],
            'donnees_manquantes' => self::textes($r['donnees_manquantes'], 'donnees_manquantes'),
            'ameliorations'      => self::textes($r['ameliorations'], 'ameliorations'),
            'projets_cites'      => self::identifiants($r['projets_cites'], $idsProjets),
        ];

        if (! in_array($propre['recommandation']['strategie'], $strategies, true)) {
            throw new ReponseIAInvalide('La stratégie recommandée par l\'IA n\'existe pas dans les résultats calculés.');
        }
        $propre['recommandation'] = [
            'strategie'     => $propre['recommandation']['strategie'],
            'justification' => self::texte($propre['recommandation']['justification'], 'justification'),
        ];

        if (! is_array($r['strategies']) || ! array_is_list($r['strategies']) || count($r['strategies']) > count($strategies)) {
            throw new ReponseIAInvalide('La liste des stratégies analysées par l\'IA est invalide.');
        }
        foreach ($r['strategies'] as $s) {
            $s = self::objet($s, ['strategie', 'points_forts', 'compromis', 'risques'], 'strategies');
            if (! in_array($s['strategie'], $strategies, true)) {
                throw new ReponseIAInvalide('L\'IA commente une stratégie qui n\'a pas été calculée.');
            }
            $propre['strategies'][] = [
                'strategie'    => $s['strategie'],
                'points_forts' => self::textes($s['points_forts'], 'points_forts'),
                'compromis'    => self::textes($s['compromis'], 'compromis'),
                'risques'      => self::textes($s['risques'], 'risques'),
            ];
        }

        self::verifierChiffres($propre, $nombres);

        return $propre;
    }

    /** Réponse à l'explication des alertes. */
    public static function alertes(mixed $reponse, array $codes, array $nombres): array
    {
        $r = self::objet($reponse, ['synthese', 'actions', 'donnees_manquantes']);

        if (! is_array($r['actions']) || ! array_is_list($r['actions']) || count($r['actions']) > self::ELEMENTS_MAX) {
            throw new ReponseIAInvalide('La liste des actions proposées par l\'IA est invalide.');
        }

        $propre = [
            'synthese'           => self::texte($r['synthese'], 'synthese'),
            'actions'            => [],
            'donnees_manquantes' => self::textes($r['donnees_manquantes'], 'donnees_manquantes'),
        ];

        foreach ($r['actions'] as $a) {
            $a = self::objet($a, ['code', 'action'], 'actions');
            if (! in_array($a['code'], $codes, true)) {
                throw new ReponseIAInvalide('L\'IA cite une alerte qui n\'a pas été détectée.');
            }
            $propre['actions'][] = ['code' => $a['code'], 'action' => self::texte($a['action'], 'action')];
        }

        self::verifierChiffres($propre, $nombres);

        return $propre;
    }

    /** Réponse au rapport mensuel. */
    public static function rapport(mixed $reponse, array $nombres): array
    {
        $r = self::objet($reponse, ['synthese', 'faits_marquants', 'points_attention', 'recommandations']);

        $propre = [
            'synthese'         => self::texte($r['synthese'], 'synthese'),
            'faits_marquants'  => self::textes($r['faits_marquants'], 'faits_marquants'),
            'points_attention' => self::textes($r['points_attention'], 'points_attention'),
            'recommandations'  => self::textes($r['recommandations'], 'recommandations'),
        ];

        self::verifierChiffres($propre, $nombres);

        return $propre;
    }

    /**
     * Nombres cités dans un texte qui n'existent pas dans les données autorisées.
     *
     * @param list<float> $autorises
     * @return list<string>
     */
    public static function chiffresNonVerifies(string $texte, array $autorises): array
    {
        preg_match_all('/\d(?:[\d\x{00A0}\x{202F} ]*\d)?(?:[.,]\d+)?/u', $texte, $trouves);
        $refuses = [];

        foreach ($trouves[0] as $brut) {
            $valeur = (float) str_replace(',', '.', preg_replace('/[\x{00A0}\x{202F} ]/u', '', $brut));
            if ($valeur <= self::PETIT_NOMBRE || self::autorise($valeur, $autorises)) {
                continue;
            }
            $refuses[] = trim($brut);
        }

        return array_values(array_unique($refuses));
    }

    /** Toutes les valeurs numériques d'un tableau (récursif) : ce sont les seuls chiffres que l'IA peut citer. */
    public static function nombres(array $donnees): array
    {
        $nombres = [];
        array_walk_recursive($donnees, function ($v) use (&$nombres) {
            if (is_int($v) || is_float($v)) {
                $nombres[] = (float) $v;
            } elseif (is_string($v)) {
                // les textes calculés (risques, hypothèses) contiennent aussi des chiffres autorisés
                preg_match_all('/\d(?:[\d\x{00A0}\x{202F} ]*\d)?(?:[.,]\d+)?/u', $v, $m);
                foreach ($m[0] as $brut) {
                    $nombres[] = (float) str_replace(',', '.', preg_replace('/[\x{00A0}\x{202F} ]/u', '', $brut));
                }
            }
        });
        $nombres[] = (float) date('Y');

        return array_values(array_unique($nombres, SORT_REGULAR));
    }

    // ---------- Outils ----------

    private static function verifierChiffres(array $propre, array $nombres): void
    {
        $texte = '';
        array_walk_recursive($propre, function ($v) use (&$texte) {
            if (is_string($v)) {
                $texte .= ' ' . $v;
            }
        });

        $refuses = self::chiffresNonVerifies($texte, $nombres);
        if ($refuses) {
            throw new ReponseIAInvalide('L\'IA a cité des chiffres absents des résultats calculés (' . implode(', ', array_slice($refuses, 0, 3)) . ').');
        }
    }

    private static function autorise(float $valeur, array $autorises): bool
    {
        foreach ($autorises as $a) {
            // tolérance : arrondi d'affichage (ex : 66,1 % pour 66.08, 1,2 million pour 1 200 000 est refusé)
            if (abs($valeur - $a) <= max(0.051, abs($a) * 0.0005)) {
                return true;
            }
        }

        return false;
    }

    private static function objet(mixed $valeur, array $cles, string $nom = 'réponse'): array
    {
        if (! is_array($valeur) || array_is_list($valeur) && $valeur !== []) {
            throw new ReponseIAInvalide("Réponse de l'IA invalide : « {$nom} » n'est pas un objet.");
        }
        foreach ($cles as $cle) {
            if (! array_key_exists($cle, $valeur)) {
                throw new ReponseIAInvalide("Réponse de l'IA incomplète : « {$cle} » manque.");
            }
        }

        return $valeur;
    }

    private static function texte(mixed $valeur, string $nom): string
    {
        if (! is_string($valeur) || trim($valeur) === '') {
            throw new ReponseIAInvalide("Réponse de l'IA invalide : « {$nom} » doit être un texte non vide.");
        }
        if (mb_strlen($valeur) > self::LONGUEUR_MAX) {
            throw new ReponseIAInvalide("Réponse de l'IA trop longue (« {$nom} »).");
        }

        // Caractères de contrôle supprimés ; l'échappement HTML est fait à l'affichage
        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valeur));
    }

    private static function textes(mixed $valeur, string $nom): array
    {
        if (! is_array($valeur) || ! array_is_list($valeur) || count($valeur) > self::ELEMENTS_MAX) {
            throw new ReponseIAInvalide("Réponse de l'IA invalide : « {$nom} » doit être une liste courte.");
        }

        return array_map(fn ($t) => self::texte($t, $nom), $valeur);
    }

    private static function identifiants(mixed $valeur, array $autorises): array
    {
        if (! is_array($valeur) || ! array_is_list($valeur)) {
            throw new ReponseIAInvalide('Réponse de l\'IA invalide : « projets_cites » doit être une liste.');
        }
        foreach ($valeur as $id) {
            if (! is_int($id) || ! in_array($id, $autorises, true)) {
                throw new ReponseIAInvalide('L\'IA cite un projet absent des données envoyées.');
            }
        }

        return array_values(array_unique($valeur));
    }
}
