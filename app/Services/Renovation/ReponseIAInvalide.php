<?php

namespace App\Services\Renovation;

use RuntimeException;

// Réponse de l'IA écartée par les garde-fous (structure, stratégie, projet ou chiffre non vérifiable)
class ReponseIAInvalide extends RuntimeException
{
}
