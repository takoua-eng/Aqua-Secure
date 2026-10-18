<?php

/*
 * Binôme 3 — Financement des rénovations : configuration de l'IA du module.
 * Les clés restent dans le .env (jamais dans Git).
 *
 * Fournisseur utilisé, dans cet ordre :
 *   1. Groq (gratuit, API au format OpenAI) si GROQ_API_KEY est renseignée ;
 *   2. Anthropic (Claude) si ANTHROPIC_API_KEY est renseignée (configuration commune, config/services.php) ;
 *   3. sinon : mode secours rédigé par des règles fixes.
 */
return [
    'ia' => [
        'groq' => [
            'key'   => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
            // Modèle utilisé si le premier a atteint son quota par minute (Groq limite chaque modèle séparément)
            'model_relais' => env('GROQ_MODEL_RELAIS', 'openai/gpt-oss-20b'),
            'url'   => env('GROQ_URL', 'https://api.groq.com/openai/v1/chat/completions'),
        ],
        'delai' => 60,   // secondes

        // Modèles entraînés (page « Modèles IA ») : ajouter l'historique synthétique documenté aux données de la base.
        // Mettre à false quand la base contient assez de vrais devis et financements.
        'jeu_synthetique' => (bool) env('RENOVATION_JEU_SYNTHETIQUE', true),
    ],
];
