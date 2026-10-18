#!/bin/sh
# =====================================================================
# Démarrage du conteneur AquaSecure.
# La configuration vient des variables d'environnement (ConfigMap + Secret Kubernetes) :
# elle est mise en cache au démarrage, puis la commande demandée est lancée
# (Apache par défaut, "php artisan migrate --force" ou "php artisan schedule:work").
# =====================================================================
set -e

# storage/app est un volume partagé (Kubernetes) : on recrée ses sous-dossiers s'il est vide
#   public  : photos de profil (servies via public/storage)
#   private : modèles d'IA entraînés (détection de contamination, rénovation, incidents)
mkdir -p storage/app/public storage/app/private

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"