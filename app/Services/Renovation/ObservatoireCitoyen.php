<?php

namespace App\Services\Renovation;

use App\Models\Financement;
use App\Models\ProjetRenovation;
use App\Models\ReseauEau;
use App\Models\Zone;
use App\Services\ScoreRisque;
use Illuminate\Support\Facades\Schema;

/*
 * Binôme 3 — « Observatoire citoyen des rénovations » : traductions en langage simple des données RÉELLES.
 * Rien n'est inventé : chaque phrase vient d'une donnée enregistrée (réseau, zone, historique, financements)
 * ou d'un barème déjà documenté par l'application (durée de vie habituelle des matériaux).
 */
class ObservatoireCitoyen
{
    private static ?bool $participation = null;

    /** Les tables de participation existent-elles ? (migration 2026_10_18_300004 exécutée) */
    public static function participationDisponible(): bool
    {
        return self::$participation ??= Schema::hasTable('soutiens_projets') && Schema::hasTable('questions_projets');
    }

    public static function oublier(): void
    {
        self::$participation = null;
    }

    /**
     * « Pourquoi ce chantier ? » : faits vérifiables sur l'élément de réseau concerné.
     *
     * @return list<array{icone: string, titre: string, texte: string, ton: string}>
     */
    public static function pourquoi(?ReseauEau $reseau, string $langue = 'fr'): array
    {
        if (! $reseau) {
            return [];
        }

        $t = fn (string $cle, array $p = []) => __("observatoire.{$cle}", $p, $langue);
        $libelles = fn (string $groupe, ?string $cle) => __("observatoire.{$groupe}", [], $langue)[$cle] ?? (string) $cle;

        $faits = [];
        $annee = $reseau->date_mise_en_service?->format('Y');
        $age = $reseau->age();
        $dureeVie = ScoreRisque::DUREE_VIE[$reseau->materiau] ?? null;

        $faits[] = [
            'icone' => 'bi-calendar3', 'ton' => 'primary', 'titre' => $t('pq_service_titre', ['age' => $age]),
            'texte' => $t('pq_service_texte', ['type' => $libelles('types', $reseau->type), 'materiau' => $libelles('materiaux', $reseau->materiau), 'annee' => $annee]),
        ];

        if ($dureeVie) {
            $part = (int) round(100 * $age / $dureeVie);
            $faits[] = $age >= $dureeVie
                ? ['icone' => 'bi-hourglass-bottom', 'ton' => 'danger', 'titre' => $t('pq_vie_depassee_titre'),
                   'texte' => $t('pq_vie_depassee_texte', ['duree' => $dureeVie])]
                : ['icone' => 'bi-hourglass-split', 'ton' => $part >= 75 ? 'warning' : 'secondary', 'titre' => $t('pq_vie_titre', ['part' => $part]),
                   'texte' => $t('pq_vie_texte', ['duree' => $dureeVie])];
        }

        if ($reseau->etat !== 'bon') {
            $depuis = $reseau->etatDepuis();
            $faits[] = [
                'icone' => $reseau->etat === 'hors_service' ? 'bi-x-octagon' : 'bi-exclamation-triangle',
                'ton' => $reseau->etat === 'hors_service' ? 'danger' : 'warning',
                'titre' => $t('pq_etat_titre', ['etat' => $libelles('etats', $reseau->etat)]),
                'texte' => $depuis ? $t('pq_etat_depuis', ['date' => $depuis->format('d/m/Y')]) : $t('pq_etat_dernier'),
            ];
        } else {
            $faits[] = ['icone' => 'bi-shield-check', 'ton' => 'success', 'titre' => $t('pq_bon_titre'), 'texte' => $t('pq_bon_texte')];
        }

        if ($reseau->materiau === 'amiante_ciment') {
            $faits[] = ['icone' => 'bi-recycle', 'ton' => 'info', 'titre' => $t('pq_amiante_titre'), 'texte' => $t('pq_amiante_texte')];
        }

        if ($population = (int) $reseau->zone?->population) {
            $faits[] = ['icone' => 'bi-people', 'ton' => 'primary',
                        'titre' => $t('pq_population_titre', ['population' => number_format($population, 0, ',', ' ')]),
                        'texte' => $t('pq_population_texte', ['zone' => $reseau->zone->nom])];
        }

        return $faits;
    }

    /** Langue de l'observatoire : « fr » ou « ar ». */
    public static function langue(?string $valeur): string
    {
        return in_array($valeur, ['fr', 'ar'], true) ? $valeur : 'fr';
    }

    /**
     * « Sur 100 DT investis » : part entière de chaque source, dont la somme fait exactement 100
     * (méthode du plus fort reste).
     *
     * @param array<string, float> $sources
     * @return list<array{source: string, libelle: string, parts: int, montant: float}>
     */
    public static function surCent(array $sources, string $langue = 'fr'): array
    {
        $total = array_sum($sources);
        if ($total <= 0) {
            return [];
        }

        $libelles = __('observatoire.sources', [], $langue);
        $lignes = [];
        foreach ($sources as $source => $montant) {
            $exact = 100 * $montant / $total;
            $lignes[] = ['source' => $source, 'libelle' => is_array($libelles) ? ($libelles[$source] ?? Financement::libelleSourceDe($source)) : Financement::libelleSourceDe($source), 'parts' => (int) floor($exact),
                         'reste' => $exact - floor($exact), 'montant' => round((float) $montant, 2)];
        }

        $manque = 100 - array_sum(array_column($lignes, 'parts'));
        $ordre = array_keys($lignes);
        usort($ordre, fn ($a, $b) => $lignes[$b]['reste'] <=> $lignes[$a]['reste'] ?: strcmp($lignes[$a]['source'], $lignes[$b]['source']));
        foreach (array_slice($ordre, 0, $manque) as $i) {
            $lignes[$i]['parts']++;
        }

        usort($lignes, fn ($a, $b) => $b['parts'] <=> $a['parts']);

        return array_map(fn ($l) => array_diff_key($l, ['reste' => 1]), $lignes);
    }

    /** Position d'un projet : celle du réseau si elle est connue, sinon le centre de la commune. */
    public static function position(ProjetRenovation $projet): ?array
    {
        $r = $projet->reseau;
        if ($r?->latitude !== null && $r?->longitude !== null) {
            return [$r->latitude, $r->longitude, 'exacte'];
        }

        $coords = $r?->zone ? Zone::coordonneesCommune($r->zone->commune) : null;

        return $coords ? [$coords[0], $coords[1], 'commune'] : null;
    }

    public static function couleurSource(string $source): string
    {
        return ['Etat' => '#0b6fb8', 'municipalite' => '#198754', 'bailleur' => '#6f42c1', 'don' => '#d63384'][$source] ?? '#6c757d';
    }

    /** [libellé, couleur Bootstrap, icône, couleur hexadécimale] d'un statut, dans la langue demandée. */
    public static function statut(string $statut, string $langue = 'fr'): array
    {
        $style = [
            'planifie' => ['primary', 'bi-calendar-event', '#0b6fb8'],
            'en_cours' => ['warning', 'bi-cone-striped', '#f59f00'],
            'termine'  => ['success', 'bi-check-circle-fill', '#198754'],
        ][$statut] ?? ['secondary', 'bi-question-circle', '#6c757d'];

        return [__('observatoire.statuts', [], $langue)[$statut] ?? '—', ...$style];
    }
}
