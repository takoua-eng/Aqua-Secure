<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Exceptions\ServiceIAException;
use App\Models\ReseauEau;
use App\Models\Zone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/*
 * Rapport d'analyse d'une zone rédigé par l'IA.
 * On envoie les données de la zone (réseaux, scores de risque, prédictions Weibull, historique, groupe K-means)
 * et le modèle renvoie un diagnostic en français avec des actions priorisées.
 *
 * Fournisseur, dans cet ordre (mêmes clés que le module Rénovation, voir config/renovation.php) :
 *   1. Groq (gratuit) si GROQ_API_KEY est renseignée ;
 *   2. Claude (Anthropic) si ANTHROPIC_API_KEY est renseignée ;
 *   3. sinon, ou si l'IA est indisponible : rapport de secours rédigé par des règles (mode démo).
 * Un rapport IA est gardé en cache tant que les données de la zone ne changent pas.
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
     * @throws ServiceIAException avec un message lisible par le gestionnaire
     */
    public static function analyser(Zone $zone): array
    {
        $fournisseur = self::fournisseur();

        // Aucune clé d'IA : rapport de démonstration rédigé par des règles
        if ($fournisseur === 'demo') {
            return self::rapportDemo($zone);
        }

        $donnees = self::donnees($zone);

        // Même données = même rapport : on évite de refaire un appel identique (et de consommer le quota).
        // Cache::remember ne met rien en cache si l'appel échoue : seul un vrai rapport est gardé 24 h.
        $cle = 'analyse-zone:' . $fournisseur . ':' . $zone->id . ':' . hash('sha256', json_encode($donnees));

        try {
            return Cache::remember($cle, now()->addDay(), fn () => $fournisseur === 'groq'
                ? self::appelerGroq($donnees)
                : self::appelerClaude($donnees));
        } catch (RuntimeException $e) {
            // IA indisponible (quota, réseau, clé…) : on affiche quand même le rapport de secours, en le signalant
            Log::warning('Analyse IA de zone indisponible', ['fournisseur' => $fournisseur, 'raison' => $e->getMessage()]);

            return self::rapportDemo($zone) + ['avertissement' => $e->getMessage()];
        }
    }

    // Fournisseur d'IA utilisé : groq, anthropic, ou demo (aucune clé)
    public static function fournisseur(): string
    {
        return match (true) {
            filled(config('renovation.ia.groq.key'))  => 'groq',
            filled(config('services.anthropic.key')) => 'anthropic',
            default                                  => 'demo',
        };
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

    /*
     * Groq (gratuit, API au format OpenAI) : même consigne que pour Claude, réponse en Markdown.
     * Offre gratuite : quota par minute et par modèle ; en cas de quota atteint (429), on réessaie
     * une fois avec le modèle de relais (GROQ_MODEL_RELAIS), comme le module Rénovation.
     */
    private static function appelerGroq(array $donnees): array
    {
        $modele = config('renovation.ia.groq.model');
        $corps = [
            'model'                 => $modele,
            'temperature'           => 0.3,
            'max_completion_tokens' => 2000,
            'reasoning_effort'      => 'low',
            'messages'              => [
                ['role' => 'system', 'content' => self::SYSTEME],
                ['role' => 'user', 'content' => self::consigne($donnees)],
            ],
        ];

        $reponse = self::requeteGroq($corps);

        $relais = config('renovation.ia.groq.model_relais');
        if (in_array($reponse->status(), [413, 429], true) && filled($relais) && $relais !== $modele) {
            $corps['model'] = $modele = $relais;
            $reponse = self::requeteGroq($corps);
        }

        match (true) {
            $reponse->successful()     => null,
            $reponse->status() === 401 => throw new ServiceIAException('La clé GROQ_API_KEY est invalide : vérifiez-la dans le fichier .env.'),
            $reponse->status() === 429 => throw new ServiceIAException("Quota gratuit de l'IA atteint : réessayez dans une minute."),
            default                    => throw new ServiceIAException("Le service d'IA a renvoyé une erreur ({$reponse->status()})."),
        };

        $choix = $reponse->json('choices.0');
        $texte = trim((string) ($choix['message']['content'] ?? ''));

        if (($choix['finish_reason'] ?? null) === 'length' || $texte === '') {
            throw new ServiceIAException("La réponse de l'IA est vide ou incomplète.");
        }

        return self::resultatIA($texte, ($reponse->json('model') ?? $modele) . ' (Groq)');
    }

    private static function requeteGroq(array $corps): \Illuminate\Http\Client\Response
    {
        try {
            return \Illuminate\Support\Facades\Http::withToken(config('renovation.ia.groq.key'))
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(config('renovation.ia.delai', 60))
                ->post(config('renovation.ia.groq.url'), $corps);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            throw new ServiceIAException("Le service d'IA est injoignable (connexion Internet ou délai dépassé).");
        }
    }

    // Message envoyé au modèle : la demande + les données de la zone en JSON
    private static function consigne(array $donnees): string
    {
        return "Analyse cette zone et propose un plan d'action.\n\n```json\n"
            . json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            . "\n```";
    }

    // Le Markdown du modèle devient du HTML ; tout HTML brut éventuel est échappé
    private static function resultatIA(string $texte, string $modele): array
    {
        return [
            'html'     => Str::markdown($texte, ['html_input' => 'escape', 'allow_unsafe_links' => false]),
            'genereLe' => now()->format('d/m/Y à H:i'),
            'modele'   => $modele,
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
                messages: [['role' => 'user', 'content' => self::consigne($donnees)]],
                outputConfig: ['effort' => 'medium'],
                // Si le modèle refuse la demande, l'API la relance automatiquement sur un modèle de secours
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException) {
            throw new ServiceIAException("La clé ANTHROPIC_API_KEY est invalide : vérifiez-la dans le fichier .env.");
        } catch (RateLimitException) {
            throw new ServiceIAException("Trop de demandes envoyées à l'IA : réessayez dans une minute.");
        } catch (APIConnectionException) {
            throw new ServiceIAException("Impossible de joindre le service d'IA : vérifiez la connexion Internet du serveur.");
        } catch (APIStatusException $e) {
            Log::warning('Analyse IA : erreur API', ['type' => $e->type?->value, 'message' => $e->getMessage()]);
            throw new ServiceIAException("Le service d'IA a renvoyé une erreur. Réessayez plus tard.");
        }

        if ($reponse->stopReason === 'refusal') {
            throw new ServiceIAException("L'IA n'a pas pu analyser cette zone. Réessayez plus tard.");
        }

        $texte = collect($reponse->content)
            ->filter(fn ($bloc) => $bloc->type === 'text')
            ->map(fn ($bloc) => $bloc->text)
            ->implode("\n");

        if (blank($texte)) {
            throw new ServiceIAException("L'IA n'a renvoyé aucun texte. Réessayez.");
        }

        return self::resultatIA($texte, (string) $reponse->model);
    }
}
