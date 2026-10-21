<?php

namespace App\Exceptions;

use RuntimeException;

/*
 * Erreur d'un service d'intelligence artificielle (Groq ou Claude) :
 * clé invalide, quota atteint, service injoignable, réponse vide…
 * Le message est lisible par le gestionnaire et affiché tel quel.
 * Elle hérite de RuntimeException : les contrôleurs qui l'attrapent déjà n'ont pas à changer.
 */
class ServiceIAException extends RuntimeException
{
}
