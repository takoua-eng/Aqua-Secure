<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Models\ReseauEau;
use App\Models\Zone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/*
 * Rapport d'analyse d'une zone rédigé par l'IA (Claude, API Anthropic).
 * On envoie les données de la zone (réseaux, scores de risque, historique, groupe K-means)
 * et Claude renvoie un diagnostic en français avec des actions priorisées.
 * Le rapport est gardé en cache tant que les données de la zone ne changent pas.
 */
class AnalyseZoneIA
{
    private const SYSTEME = <<<'TXT'
        Tu es un ingénieur expert en exploitation de réseaux d'eau potable en Tunisie (type SONEDE).
        Tu aides un gestionnaire à décider quoi faire dans une zone, à partir des données JSON fournies.

        Règles :
        - Base-toi uniquement sur les données fournies ; n'invente ni chiffres, ni incidents, ni coûts.
        - Cite les éléments par leur code (ex : CAN-TUN-001).
        - Le score de risque (0-100) et ses facteurs sont calculés par l'application : appuie-toi dessus.
        - prediction_weibull donne la probabilité de panne en % à 1 an et à 5 ans, et la vie restante médiane
          en années (loi de Weibull selon le matériau et l'âge) ; ce sont des estimations, présente-les comme telles.
        - Si une donnée manque pour conclure, dis-le simplement.
        - Réponds en français, en Markdown, en 250 mots maximum, avec exactement ces sections :
          ## Synthèse
          ## Points critiques
          ## Actions recommandées   (liste numérotée, de la plus urgente à la moins urgente)
          ## À surveiller
        TXT;

    /**
     * @return array{html: string, genereLe: string, modele: string}
     *
     * @throws RuntimeException avec un message lisible par le gestionnaire
     */
    public static function analyser(Zone $zone): array
    {
        // Pas de clé API (compte payant) : rapport de démonstration rédigé par des règles, sans IA
        if (blank(config('services.anthropic.key'))) {
            return self::rapportDemo($zone);
        }

        $donnees = self::donnees($zone);

        // Même données = même rapport : on évite de repayer un appel identique
        $cle = 'analyse-ia:zone:' . $zone->id . ':' . md5(json_encode($donnees));

        return Cache::remember($cle, now()->addDay(), fn () => self::appelerClaude($donnees));
    }

    // Données envoyées à Claude (rien de personnel : seulement l'infrastructure de la zone)
    public static function donnees(Zone $zone): array
    {
        $zone->load(['reseaux' => fn ($q) => $q->orderBy('code')->with(['historiques' => fn ($h) => $h->limit(5)])]);
        $groupe = GroupesZones::calculer()['parZone'][$zone->id]['libelle'] ?? null;

        return [
            'zone' => [
                'nom'        => $zone->nom,
                'commune'    => $zone->commune,
                'population' => $zone->population,
                'score_risque_zone' => ScoreRisque::pourZone($zone),
                'groupe_kmeans' => $groupe,
            ],
            'reseaux' => $zone->reseaux->map(function (ReseauEau $r) {
                $risque = ScoreRisque::pourReseau($r);

                return [
                    'code'      => $r->code,
                    'type'      => $r->libelleType(),
                    'materiau'  => $r->libelleMateriau(),
                    'age_ans'   => $r->age(),
                    'duree_vie_habituelle_ans' => ScoreRisque::DUREE_VIE[$r->materiau] ?? null,
                    'etat'      => $r->libelleEtat(),
                    'score_risque' => $risque['score'],
                    'niveau_risque' => $risque['libelle'],
                    'facteurs'  => collect($risque['facteurs'])->map(fn ($f) => "{$f['libelle']} : {$f['points']}/{$f['max']}")->all(),
                    'prediction_weibull' => ProbabiliteDefaillance::pourReseau($r),
                    'historique_etats' => $r->historiques->map(fn ($h) => [
                        'date' => $h->created_at->format('Y-m-d'),
                        'de'   => $h->ancien_etat ? ReseauEau::libelleEtatDe($h->ancien_etat) : 'création',
                        'vers' => ReseauEau::libelleEtatDe($h->nouvel_etat),
                    ])->all(),
                ];
            })->all(),
            'date_du_jour' => now()->format('Y-m-d'),
        ];
    }

    /*
     * Mode démo : même plan que le rapport de Claude, mais écrit par des règles fixes
     * à partir du score de risque. Gratuit, instantané, et signalé comme tel à l'écran.
     */
    public static function rapportDemo(Zone $zone): array
    {
        $zone->load(['reseaux' => fn ($q) => $q->orderBy('code')->withMax('historiques as etat_depuis', 'created_at')]);
        $reseaux = $zone->reseaux->each(fn (ReseauEau $r) => $r->setRelation('zone', $zone));

        if ($reseaux->isEmpty()) {
            $markdown = "## Synthèse\nLa zone **{$zone->nom}** ({$zone->commune}) ne contient encore aucun élément de réseau : aucune analyse possible.";

            return self::resultatDemo($markdown);
        }

        // Chaque élément avec son score, du plus risqué au moins risqué
        $lignes = $reseaux->map(fn (ReseauEau $r) => ['r' => $r, 'risque' => ScoreRisque::pourReseau($r)])
            ->sortByDesc(fn ($l) => $l['risque']['score'])->values();

        $zoneRisque = ScoreRisque::pourZone($zone);
        $groupe = GroupesZones::calculer()['parZone'][$zone->id]['libelle'] ?? null;
        $parEtat = $reseaux->countBy('etat');
        $population = number_format($zone->population, 0, ',', ' ');

        // --- Synthèse
        $etats = collect(['bon' => 'en bon état', 'degrade' => 'dégradé', 'hors_service' => 'hors service'])
            ->filter(fn ($texte, $cle) => isset($parEtat[$cle]))
            ->map(fn ($texte, $cle) => $parEtat[$cle] . ' ' . ($cle === 'degrade' && $parEtat[$cle] > 1 ? 'dégradés' : $texte))
            ->join(', ', ' et ');
        $synthese = "La zone **{$zone->nom}** ({$zone->commune}, {$population} habitants) compte {$reseaux->count()} élément(s) : {$etats}. "
            . "Son niveau de risque est **{$zoneRisque['libelle']}** ({$zoneRisque['score']}/100, élément le plus exposé)"
            . ($groupe ? " et elle est classée dans le groupe **{$groupe}**." : '.');

        // --- Points critiques : mauvais état ou risque au moins élevé
        $critiques = $lignes->filter(fn ($l) => $l['r']->etat !== 'bon' || $l['risque']['score'] >= 55);
        $points = $critiques->map(function ($l) {
            $r = $l['r'];
            $principal = collect($l['risque']['facteurs'])->sortByDesc('points')->first();

            $prediction = ProbabiliteDefaillance::pourReseau($r);
            $panne = $prediction['horsService'] ? '' : ", {$prediction['cinq_ans']} % de risque de panne d'ici 5 ans";

            return "- **{$r->code}** ({$r->libelleType()}, {$r->libelleMateriau()}, {$r->age()} ans) : "
                . mb_strtolower($r->libelleEtat()) . ", risque {$l['risque']['score']}/100{$panne}. Facteur principal : "
                . mb_strtolower($principal['libelle']) . '.';
        });

        // --- Actions, de la plus urgente à la moins urgente
        $actions = $critiques->map(function ($l) {
            $r = $l['r'];
            $finDeVie = $r->age() >= (ScoreRisque::DUREE_VIE[$r->materiau] ?? 50);

            $action = match (true) {
                $r->etat === 'hors_service' => "Intervenir en urgence sur **{$r->code}** (hors service) : réparer ou remplacer pour rétablir l'alimentation",
                $finDeVie                   => "Planifier le remplacement de **{$r->code}**, qui a dépassé sa durée de vie",
                $r->etat === 'degrade'      => "Programmer une inspection et une réparation de **{$r->code}**",
                default                     => "Contrôler **{$r->code}**, dont le score de risque est élevé",
            };
            if ($r->materiau === 'amiante_ciment') {
                $action .= ' ; prévoir son remplacement par du PEHD (amiante-ciment)';
            }

            return $action . '.';
        })->values();

        // --- À surveiller : éléments en bon état mais proches de la fin de vie (80 % et plus)
        $surveiller = $lignes->filter(fn ($l) => $l['r']->etat === 'bon'
                && $l['r']->age() >= 0.8 * (ScoreRisque::DUREE_VIE[$l['r']->materiau] ?? 50))
            ->map(fn ($l) => "- **{$l['r']->code}** : en bon état mais {$l['r']->age()} ans pour une durée de vie de "
                . (ScoreRisque::DUREE_VIE[$l['r']->materiau] ?? 50) . ' ans.');

        $markdown = "## Synthèse\n{$synthese}\n\n"
            . "## Points critiques\n" . ($points->isNotEmpty() ? $points->join("\n") : "Aucun élément en mauvais état ni à risque élevé.") . "\n\n"
            . "## Actions recommandées\n" . ($actions->isNotEmpty()
                ? $actions->map(fn ($a, $i) => ($i + 1) . ". {$a}")->join("\n")
                : "1. Poursuivre l'entretien préventif habituel.") . "\n\n"
            . "## À surveiller\n" . ($surveiller->isNotEmpty() ? $surveiller->join("\n") : "Aucun élément proche de sa fin de vie.");

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
        $cleApi = config('services.anthropic.key');
        $modele = config('services.anthropic.model');
        $client = new Client(apiKey: $cleApi);

        try {
            $reponse = $client->beta->messages->create(
                model: $modele,
                maxTokens: 16000,
                system: self::SYSTEME,
                messages: [[
                    'role'    => 'user',
                    'content' => "Analyse cette zone et propose un plan d'action.\n\n```json\n"
                        . json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        . "\n```",
                ]],
                outputConfig: ['effort' => 'medium'],
                // Si le modèle refuse la demande, l'API la relance automatiquement sur un modèle de secours
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException) {
            throw new RuntimeException("La clé ANTHROPIC_API_KEY est invalide : vérifiez-la dans le fichier .env.");
        } catch (RateLimitException) {
            throw new RuntimeException("Trop de demandes envoyées à l'IA : réessayez dans une minute.");
        } catch (APIConnectionException) {
            throw new RuntimeException("Impossible de joindre le service d'IA : vérifiez la connexion Internet du serveur.");
        } catch (APIStatusException $e) {
            Log::warning('Analyse IA : erreur API', ['type' => $e->type?->value, 'message' => $e->getMessage()]);
            throw new RuntimeException("Le service d'IA a renvoyé une erreur. Réessayez plus tard.");
        }

        if ($reponse->stopReason === 'refusal') {
            throw new RuntimeException("L'IA n'a pas pu analyser cette zone. Réessayez plus tard.");
        }

        $texte = collect($reponse->content)
            ->filter(fn ($bloc) => $bloc->type === 'text')
            ->map(fn ($bloc) => $bloc->text)
            ->implode("\n");

        if (blank($texte)) {
            throw new RuntimeException("L'IA n'a renvoyé aucun texte. Réessayez.");
        }

        return [
            // Le Markdown de Claude devient du HTML ; tout HTML brut éventuel est échappé
            'html'     => Str::markdown($texte, ['html_input' => 'escape', 'allow_unsafe_links' => false]),
            'genereLe' => now()->format('d/m/Y à H:i'),
            'modele'   => $reponse->model,
        ];
    }
}
