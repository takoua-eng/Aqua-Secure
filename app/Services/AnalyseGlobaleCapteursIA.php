<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Models\Capteur;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/*
 * Analyse globale de TOUS les capteurs, regroupés par zone, rédigée par l'IA (Claude).
 * L'idée clé : si plusieurs capteurs d'une même zone sont en alerte en même temps,
 * le problème vient très probablement du réseau ; si un seul capteur est en alerte alors que
 * ses voisins sont normaux, c'est peut-être le capteur qui est défaillant.
 * Sans clé API : rapport de démonstration rédigé par des règles.
 */
class AnalyseGlobaleCapteursIA
{
    private const SYSTEME = <<<'TXT'
        Tu es un ingénieur expert en supervision (télégestion) de réseaux d'eau potable en Tunisie.
        Tu reçois l'état de tous les capteurs, regroupés par zone, calculé par l'application.

        Règles :
        - Base-toi uniquement sur les données fournies ; n'invente ni chiffres ni événements.
        - Plusieurs capteurs en alerte dans une même zone = problème probable du réseau (fuite, rupture, sécheresse).
          Un seul capteur en alerte alors que ses voisins sont normaux = vérifier d'abord le capteur.
        - Cite les capteurs et réseaux par leur code.
        - Réponds en français, en Markdown, en 300 mots maximum, avec exactement ces sections :
          ## Synthèse
          ## Zones à risque
          ## Priorités d'intervention   (liste numérotée, de la plus urgente à la moins urgente)
          ## Capteurs à vérifier
        TXT;

    /** @throws RuntimeException */
    public static function analyser(): array
    {
        $donnees = self::donnees();

        if (blank(config('services.anthropic.key'))) {
            return self::rapportDemo($donnees);
        }

        $cle = 'analyse-globale-capteurs:' . hash('sha256', json_encode($donnees));
        return Cache::remember($cle, now()->addHour(), fn () => self::appelerClaude($donnees));
    }

    // État de chaque capteur, regroupé par zone
    public static function donnees(): array
    {
        $capteurs = Capteur::with(['reseau.zone', 'dernierReleve'])->orderBy('code')->get();

        $etats = $capteurs->map(function (Capteur $c) {
            $releves = $c->releves()->orderByDesc('date_releve')->limit(50)->get()->reverse()->values();
            $anomalies = AnalyseCapteur::anomalies($releves);
            $prevision = AnalyseCapteur::prevision($c, $releves);
            $horsSeuil24h = $releves->filter(fn ($r) => $r->hors_seuil && $r->date_releve->gte(now()->subDay()))->count();

            $etat = match (true) {
                $c->statut === 'hors_service' => 'hors service',
                $c->estMuet() => 'muet',
                $horsSeuil24h > 0 => 'alerte',
                count($anomalies) > 0 => 'anomalie',
                default => 'normal',
            };

            // Score de priorité : alerte > franchissement prévu > anomalies > réseau en mauvais état
            $priorite = ($etat === 'alerte' ? 3 : 0)
                + (! empty($prevision['franchissement']) ? 2 : 0)
                + min(count($anomalies), 2)
                + ($c->reseau->etat !== 'bon' ? 1 : 0)
                + ($etat === 'muet' ? 2 : 0);

            return [
                'code'             => $c->code,
                'mesure'           => $c->libelleTypeMesure() . ' (' . $c->unite . ')',
                'statut'           => $c->libelleStatut(),
                'etat'             => $etat,
                'reseau'           => $c->reseau->code,
                'etat_reseau'      => $c->reseau->libelleEtat(),
                'zone'             => $c->reseau->zone->nom . ' (' . $c->reseau->zone->commune . ')',
                'derniere_valeur'  => $c->dernierReleve ? $c->avecUnite($c->dernierReleve->valeur) : null,
                'seuils'           => $c->plageSeuils(),
                'hors_seuil_24h'   => $horsSeuil24h,
                'anomalies'        => count($anomalies),
                'tendance'         => $prevision['tendance'] ?? null,
                'franchissement'   => AnalyseCapteur::phraseFranchissement($prevision['franchissement'] ?? null),
                'priorite'         => $priorite,
            ];
        });

        return [
            'resume' => [
                'capteurs'      => $etats->count(),
                'en_alerte'     => $etats->where('etat', 'alerte')->count(),
                'avec_anomalie' => $etats->where('etat', 'anomalie')->count(),
                'muets'         => $etats->where('etat', 'muet')->count(),
                'hors_service'  => $etats->where('etat', 'hors service')->count(),
            ],
            'zones' => $etats->groupBy('zone')->map(fn (Collection $liste, $zone) => [
                'zone'     => $zone,
                'capteurs' => $liste->values()->all(),
            ])->values()->all(),
            'date_du_jour' => now()->format('Y-m-d H:i'),
        ];
    }

    public static function rapportDemo(array $d): array
    {
        $r = $d['resume'];
        $tous = collect($d['zones'])->flatMap(fn ($z) => $z['capteurs']);

        if ($tous->isEmpty()) {
            return self::resultat("## Synthèse\nAucun capteur n'est installé : aucune analyse possible.", true);
        }

        $zonesTouchees = collect($d['zones'])->filter(fn ($z) => collect($z['capteurs'])->contains('etat', 'alerte'));

        // --- Synthèse
        $synthese = "{$r['capteurs']} capteurs surveillés : **{$r['en_alerte']} en alerte** sur les dernières 24 h, "
            . "{$r['avec_anomalie']} avec des anomalies, {$r['muets']} muet(s) et {$r['hors_service']} hors service. "
            . ($zonesTouchees->isEmpty()
                ? 'Aucune zone ne présente d\'alerte.'
                : $zonesTouchees->count() . ' zone(s) concernée(s) par des alertes.');

        // --- Zones à risque : alertes concordantes ou isolées
        $zones = $zonesTouchees->map(function ($z) {
            $capteurs = collect($z['capteurs'])->whereNotIn('etat', ['hors service']);
            $enAlerte = $capteurs->where('etat', 'alerte');
            $codes = $enAlerte->pluck('code')->map(fn ($c) => "**{$c}**")->join(', ', ' et ');

            if ($enAlerte->count() >= 2) {
                return "- **{$z['zone']}** : {$enAlerte->count()} capteurs en alerte en même temps ({$codes}). "
                    . 'Les mesures concordent : **problème du réseau très probable** (fuite, rupture ou manque d\'eau).';
            }
            if ($capteurs->count() >= 2) {
                return "- **{$z['zone']}** : seul {$codes} est en alerte, ses voisins sont normaux. "
                    . "**Vérifier d'abord le capteur** avant d'envoyer une équipe sur le réseau.";
            }

            return "- **{$z['zone']}** : {$codes} est en alerte, mais c'est le seul capteur de la zone : "
                . 'alerte impossible à confirmer par un autre capteur.';
        });

        // --- Priorités : les 5 capteurs au score le plus élevé
        $priorites = $tous->where('priorite', '>', 0)->sortByDesc('priorite')->take(5)->values()
            ->map(function ($c, $i) {
                $raisons = collect([
                    $c['etat'] === 'alerte' ? "{$c['hors_seuil_24h']} relevé(s) hors seuil sur 24 h (dernière valeur {$c['derniere_valeur']}, seuils {$c['seuils']})" : null,
                    $c['etat'] === 'muet' ? 'plus aucune mesure reçue' : null,
                    $c['franchissement'] ? mb_strtolower(rtrim($c['franchissement'], '.')) : null,
                    $c['anomalies'] ? "{$c['anomalies']} anomalie(s)" : null,
                    $c['etat_reseau'] !== 'Bon' ? "réseau {$c['reseau']} « {$c['etat_reseau']} »" : null,
                ])->filter()->join(' ; ');

                return ($i + 1) . ". **{$c['code']}** ({$c['zone']}) : {$raisons}.";
            });

        // --- Capteurs à vérifier : muets, nombreuses anomalies sans alerte, en panne
        $verifier = $tous->filter(fn ($c) => $c['etat'] === 'muet'
                || ($c['etat'] === 'anomalie' && $c['anomalies'] >= 2)
                || $c['statut'] === 'En panne')
            ->map(fn ($c) => "- **{$c['code']}** : " . match (true) {
                $c['etat'] === 'muet' => 'n\'envoie plus de mesures, contrôler l\'alimentation et la transmission.',
                $c['statut'] === 'En panne' => 'déclaré en panne, ses mesures ne sont pas fiables.',
                default => "{$c['anomalies']} pics inhabituels sans dépassement : vérifier l'étalonnage.",
            });

        $markdown = "## Synthèse\n{$synthese}\n\n"
            . "## Zones à risque\n" . ($zones->isNotEmpty() ? $zones->join("\n") : 'Aucune zone en alerte.') . "\n\n"
            . "## Priorités d'intervention\n" . ($priorites->isNotEmpty() ? $priorites->join("\n") : '1. Aucune intervention urgente.') . "\n\n"
            . "## Capteurs à vérifier\n" . ($verifier->isNotEmpty() ? $verifier->join("\n") : 'Aucun capteur suspect.');

        return self::resultat($markdown, true);
    }

    private static function resultat(string $markdown, bool $demo, ?string $modele = null): array
    {
        return [
            'html'     => Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]),
            'genereLe' => now()->format('d/m/Y à H:i'),
            'modele'   => $modele ?? 'mode démo',
            'demo'     => $demo,
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
                    'content' => "Analyse l'état de l'ensemble des capteurs.\n\n```json\n"
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
            Log::warning('Analyse globale IA : erreur API', ['message' => $e->getMessage()]);
            throw new RuntimeException("Le service d'IA a renvoyé une erreur. Réessayez plus tard.");
        }

        if ($reponse->stopReason === 'refusal') {
            throw new RuntimeException("L'IA n'a pas pu réaliser l'analyse. Réessayez plus tard.");
        }

        $texte = collect($reponse->content)
            ->filter(fn ($bloc) => $bloc->type === 'text')
            ->map(fn ($bloc) => $bloc->text)
            ->implode("\n");

        if (blank($texte)) {
            throw new RuntimeException("L'IA n'a renvoyé aucun texte. Réessayez.");
        }

        return self::resultat($texte, false, $reponse->model);
    }
}