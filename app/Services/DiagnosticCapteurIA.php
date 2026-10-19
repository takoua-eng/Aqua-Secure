<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Models\Capteur;
use App\Models\Releve;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/*
 * Diagnostic d'un capteur rédigé par l'IA (Claude, API Anthropic) — même configuration que
 * l'analyse des zones du binôme 1 (ANTHROPIC_API_KEY dans le .env).
 * On envoie les mesures, les anomalies détectées et la prévision ; Claude explique la cause probable
 * (fuite, sécheresse, capteur défaillant…) et propose des actions.
 * Sans clé API : diagnostic de démonstration rédigé par des règles, signalé comme tel à l'écran.
 */
class DiagnosticCapteurIA
{
    private const SYSTEME = <<<'TXT'
        Tu es un ingénieur expert en supervision (télégestion) de réseaux d'eau potable en Tunisie.
        Tu aides un gestionnaire à interpréter les mesures d'un capteur, à partir des données JSON fournies.

        Règles :
        - Base-toi uniquement sur les données fournies ; n'invente ni chiffres ni événements.
        - Distingue un vrai problème du réseau (fuite, rupture, sécheresse, surconsommation)
          d'un problème du capteur lui-même (valeurs figées, sauts isolés, dérive, panne de transmission).
        - "anomalies" sont détectées par un score z robuste ; "prevision" vient d'une régression linéaire
          avec un R² : présente-les comme des estimations.
        - Si une donnée manque pour conclure, dis-le.
        - Réponds en français, en Markdown, en 200 mots maximum, avec exactement ces sections :
          ## Diagnostic
          ## Cause probable
          ## Actions recommandées   (liste numérotée, de la plus urgente à la moins urgente)
          ## Fiabilité du capteur
        TXT;

    /** @throws RuntimeException avec un message lisible par le gestionnaire */
    public static function diagnostiquer(Capteur $capteur): array
    {
        $donnees = self::donnees($capteur);

        if (blank(config('services.anthropic.key'))) {
            return self::diagnosticDemo($capteur, $donnees);
        }

        // Mêmes données = même diagnostic : on évite de repayer un appel identique
        $cle = 'diagnostic-ia:capteur:' . $capteur->id . ':' . hash('sha256', json_encode($donnees));
        return Cache::remember($cle, now()->addDay(), fn () => self::appelerClaude($donnees));
    }

    // Données envoyées à Claude (aucune donnée personnelle)
    public static function donnees(Capteur $capteur): array
    {
        $capteur->loadMissing('reseau.zone');
        $releves = $capteur->releves()->orderByDesc('date_releve')->limit(50)->get()->reverse()->values();
        $anomalies = AnalyseCapteur::anomalies($releves);
        $prevision = AnalyseCapteur::prevision($capteur, $releves);
        $dernier = $releves->last();

        return [
            'capteur' => [
                'code'              => $capteur->code,
                'modele'            => $capteur->modele,
                'mesure'            => $capteur->libelleTypeMesure(),
                'unite'             => $capteur->unite,
                'seuil_min'         => $capteur->seuil_min,
                'seuil_max'         => $capteur->seuil_max,
                'statut'            => $capteur->libelleStatut(),
                'installe_le'       => $capteur->date_installation->format('Y-m-d'),
                'heures_depuis_dernier_releve' => $dernier ? (int) $dernier->date_releve->diffInHours(now()) : null,
            ],
            'reseau' => [
                'code'     => $capteur->reseau->code,
                'type'     => $capteur->reseau->libelleType(),
                'materiau' => $capteur->reseau->libelleMateriau(),
                'age_ans'  => $capteur->reseau->age(),
                'etat'     => $capteur->reseau->libelleEtat(),
                'zone'     => $capteur->reseau->zone->nom . ' (' . $capteur->reseau->zone->commune . ')',
            ],
            'statistiques_50_derniers' => [
                'nombre'     => $releves->count(),
                'min'        => $releves->min('valeur'),
                'max'        => $releves->max('valeur'),
                'moyenne'    => $releves->isEmpty() ? null : round($releves->avg('valeur'), 2),
                'hors_seuil' => $releves->where('hors_seuil', true)->count(),
            ],
            'releves_recents' => $releves->take(-15)->map(fn (Releve $r) => [
                'date'       => $r->date_releve->format('Y-m-d H:i'),
                'valeur'     => $r->valeur,
                'hors_seuil' => $r->hors_seuil,
                'anomalie'   => isset($anomalies[$r->id]),
                'remarque'   => $r->remarque,
            ])->values()->all(),
            'anomalies' => $releves->filter(fn (Releve $r) => isset($anomalies[$r->id]))
                ->map(fn (Releve $r) => [
                    'date'    => $r->date_releve->format('Y-m-d H:i'),
                    'valeur'  => $r->valeur,
                    'attendu' => $anomalies[$r->id]['attendu'],
                    'score_z' => $anomalies[$r->id]['score'],
                ])->values()->all(),
            'prevision' => $prevision ? [
                'tendance'           => $prevision['tendance'],
                'variation_par_jour' => $prevision['pente_jour'],
                'valeur_dans_24h'    => $prevision['valeur_24h'],
                'r2'                 => $prevision['r2'],
                'franchissement'     => AnalyseCapteur::phraseFranchissement($prevision['franchissement']),
            ] : null,
            'date_du_jour' => now()->format('Y-m-d H:i'),
        ];
    }

    /*
     * Mode démo : même plan que le diagnostic de Claude, écrit par des règles fixes. Gratuit et instantané.
     */
    public static function diagnosticDemo(Capteur $capteur, array $d): array
    {
        $stats = $d['statistiques_50_derniers'];
        $prevision = $d['prevision'];
        $nbAnomalies = count($d['anomalies']);
        $recents = collect($d['releves_recents']);
        $derniers = $recents->take(-4);
        $mesure = mb_strtolower($capteur->libelleTypeMesure());

        if ($stats['nombre'] === 0) {
            return self::resultatDemo("## Diagnostic\nLe capteur **{$capteur->code}** n'a encore envoyé aucune mesure : aucun diagnostic possible.");
        }

        $sousMin = $derniers->filter(fn ($r) => $r['valeur'] < $capteur->seuil_min)->count();
        $surMax = $derniers->filter(fn ($r) => $r['valeur'] > $capteur->seuil_max)->count();
        $figes = $recents->take(-6)->pluck('valeur')->unique()->count() === 1 && $recents->count() >= 6;
        $muet = ($d['capteur']['heures_depuis_dernier_releve'] ?? 0) > Capteur::HEURES_SILENCE;

        // --- Diagnostic
        $diagnostic = "Sur les {$stats['nombre']} derniers relevés, la {$mesure} varie de "
            . $capteur->avecUnite($stats['min']) . ' à ' . $capteur->avecUnite($stats['max'])
            . " (moyenne " . $capteur->avecUnite($stats['moyenne']) . ", seuils normaux : {$capteur->plageSeuils()}). "
            . "{$stats['hors_seuil']} relevé(s) hors seuil et {$nbAnomalies} anomalie(s) détectée(s).";
        if ($prevision) {
            $diagnostic .= " Tendance : **{$prevision['tendance']}** (" . ($prevision['variation_par_jour'] >= 0 ? '+' : '')
                . Capteur::nombre($prevision['variation_par_jour']) . " {$capteur->unite}/jour, R² = {$prevision['r2']}).";
        }

        // --- Cause probable
        $cause = match (true) {
            $muet => "Le capteur n'envoie plus de mesures : panne de transmission ou d'alimentation probable.",
            $figes => 'Les dernières valeurs sont identiques : le capteur est probablement bloqué (valeur figée).',
            $sousMin >= 2 && $capteur->type_mesure === 'pression' => "Chute de pression durable sur **{$d['reseau']['code']}** : une **fuite** ou une rupture de canalisation est la cause la plus probable"
                . ($d['reseau']['etat'] !== 'Bon' ? ", d'autant que le réseau est déjà « {$d['reseau']['etat']} »." : '.'),
            $sousMin >= 2 && $capteur->type_mesure === 'niveau' => "Le niveau du réservoir baisse sous le seuil : consommation supérieure à l'alimentation (sécheresse, fuite en aval ou pompage insuffisant).",
            $sousMin >= 2 && $capteur->type_mesure === 'debit' => 'Débit trop faible : arrêt partiel de la station, vanne fermée ou baisse de la ressource (sécheresse).',
            $surMax >= 2 => "Valeurs au-dessus du seuil maximum : surpression, surconsommation ou mauvais réglage de l'équipement.",
            $nbAnomalies > 0 => "Pics isolés sans dépassement durable : coup de bélier, manœuvre de vanne ou mesure parasite du capteur.",
            default => 'Aucune cause de dysfonctionnement : les mesures sont stables et dans les seuils.',
        };

        // --- Actions
        $actions = collect();
        if ($muet) {
            $actions->push("Vérifier l'alimentation et la transmission du capteur **{$capteur->code}** sur site.");
        }
        if ($sousMin >= 2 || $surMax >= 2) {
            $actions->push("Envoyer une équipe d'intervention sur **{$d['reseau']['code']}** ({$d['reseau']['zone']}).");
            $actions->push('Comparer avec les capteurs voisins pour confirmer que le problème vient du réseau et non du capteur.');
        }
        if ($figes || $nbAnomalies >= 2) {
            $actions->push("Contrôler l'étalonnage du capteur **{$capteur->code}** ({$capteur->modele}).");
        }
        if ($prevision && $prevision['franchissement']) {
            $actions->push('Surveiller de près : ' . lcfirst($prevision['franchissement']));
        }
        if ($actions->isEmpty()) {
            $actions->push("Poursuivre la surveillance habituelle ; aucune intervention n'est nécessaire.");
        }

        // --- Fiabilité
        $fiabilite = match (true) {
            $capteur->statut !== 'actif' => "Le capteur est déclaré « {$capteur->libelleStatut()} » : ses mesures sont à confirmer.",
            $figes || $muet => 'Faible : le capteur semble défaillant.',
            $nbAnomalies >= 3 => "Moyenne : {$nbAnomalies} anomalies ponctuelles, un étalonnage est conseillé.",
            default => 'Bonne : mesures régulières et cohérentes.',
        };

        $markdown = "## Diagnostic\n{$diagnostic}\n\n## Cause probable\n{$cause}\n\n"
            . "## Actions recommandées\n" . $actions->values()->map(fn ($a, $i) => ($i + 1) . ". {$a}")->join("\n") . "\n\n"
            . "## Fiabilité du capteur\n{$fiabilite}";

        return self::resultatDemo($markdown);
    }

    private static function resultatDemo(string $markdown): array
    {
        return [
            'html'     => Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]),
            'genereLe' => now()->format('d/m/Y à H:i'),
            'modele'   => 'mode démo',
            'demo'     => true,
        ];
    }

    private static function appelerClaude(array $donnees): array
    {
        $client = new Client(apiKey: config('services.anthropic.key'));

        try {
            $reponse = $client->messages->create(
                model: config('services.anthropic.model'),
                maxTokens: 8000,
                system: self::SYSTEME,
                messages: [[
                    'role'    => 'user',
                    'content' => "Diagnostique ce capteur.\n\n```json\n"
                        . json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        . "\n```",
                ]],
                outputConfig: ['effort' => 'medium'],
            );
        } catch (AuthenticationException) {
            throw new RuntimeException('La clé ANTHROPIC_API_KEY est invalide : vérifiez-la dans le fichier .env.');
        } catch (RateLimitException) {
            throw new RuntimeException("Trop de demandes envoyées à l'IA : réessayez dans une minute.");
        } catch (APIConnectionException) {
            throw new RuntimeException("Impossible de joindre le service d'IA : vérifiez la connexion Internet du serveur.");
        } catch (APIStatusException $e) {
            Log::warning('Diagnostic IA capteur : erreur API', ['message' => $e->getMessage()]);
            throw new RuntimeException("Le service d'IA a renvoyé une erreur. Réessayez plus tard.");
        }

        if ($reponse->stopReason === 'refusal') {
            throw new RuntimeException("L'IA n'a pas pu analyser ce capteur. Réessayez plus tard.");
        }

        $texte = collect($reponse->content)
            ->filter(fn ($bloc) => $bloc->type === 'text')
            ->map(fn ($bloc) => $bloc->text)
            ->implode("\n");

        if (blank($texte)) {
            throw new RuntimeException("L'IA n'a renvoyé aucun texte. Réessayez.");
        }

        return [
            'html'     => Str::markdown($texte, ['html_input' => 'escape', 'allow_unsafe_links' => false]),
            'genereLe' => now()->format('d/m/Y à H:i'),
            'modele'   => $reponse->model,
        ];
    }
}