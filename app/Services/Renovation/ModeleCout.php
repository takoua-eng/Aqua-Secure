<?php

namespace App\Services\Renovation;

use App\Models\ProjetRenovation;
use App\Models\ReseauEau;
use App\Services\PlanRenovation;

/*
 * Coût total d'un projet de rénovation.
 *
 * Le modèle officiel PROJET_RENOVATION ne contient pas de budget. Deux sources possibles, toujours affichées :
 *   1. « déclaré »  : coût saisi par le gestionnaire (devis) dans le champ facultatif cout_estime — fiable ;
 *   2. « estimé »   : barème de remplacement du module Analyse des risques (PlanRenovation::cout, binôme 1),
 *                     selon le type et le matériau du réseau — ordre de grandeur seulement.
 * Un taux de couverture « fiable » n'est calculé que sur les coûts déclarés.
 */
class ModeleCout
{
    public const SOURCES = [
        'declare' => 'Coût déclaré (devis)',
        'estime'  => 'Estimation (barème)',
        'inconnu' => 'Coût inconnu',
    ];

    /** @return array{montant: ?float, source: string, libelle: string, detail: string} */
    public static function pourProjet(ProjetRenovation $projet): array
    {
        if ($projet->cout_estime !== null && (float) $projet->cout_estime > 0) {
            return [
                'montant' => round((float) $projet->cout_estime, 2),
                'source'  => 'declare',
                'libelle' => self::SOURCES['declare'],
                'detail'  => 'Montant saisi par le gestionnaire.',
            ];
        }

        $reseau = $projet->reseau;

        return $reseau ? self::estimation($reseau) : [
            'montant' => null,
            'source'  => 'inconnu',
            'libelle' => self::SOURCES['inconnu'],
            'detail'  => "Le réseau du projet est introuvable : aucun coût ne peut être estimé.",
        ];
    }

    public static function estimation(ReseauEau $reseau): array
    {
        return [
            'montant' => (float) PlanRenovation::cout($reseau),
            'source'  => 'estime',
            'libelle' => self::SOURCES['estime'],
            'detail'  => "Remplacement d'un élément « {$reseau->libelleType()} » en {$reseau->libelleMateriau()}, "
                . "d'après le barème du module Analyse des risques. Ordre de grandeur à confirmer par un devis.",
        ];
    }
}
