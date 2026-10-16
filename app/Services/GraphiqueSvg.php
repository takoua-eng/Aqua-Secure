<?php

namespace App\Services;

use App\Models\Capteur;
use Illuminate\Support\Collection;

/*
 * Dessine le graphique des relevés d'un capteur en SVG, côté serveur.
 * Utilisé dans le rapport PDF : dompdf n'exécute pas JavaScript (pas d'ApexCharts),
 * on lui donne donc une image toute prête.
 */
class GraphiqueSvg
{
    public static function pourCapteur(Capteur $capteur, Collection $releves, array $anomalies = [], ?array $prevision = null): string
    {
        $l = 700; $h = 230;                       // taille de l'image
        $g = 55; $d = 15; $haut = 15; $bas = 30;  // marges

        if ($releves->isEmpty()) {
            return '';
        }

        $points = $releves->map(fn ($r) => ['t' => $r->date_releve->timestamp, 'v' => $r->valeur, 'id' => $r->id, 'hs' => $r->hors_seuil])->values();
        $prev = collect($prevision['points'] ?? [])->map(fn ($p) => ['t' => intdiv($p['x'], 1000), 'v' => $p['y']]);

        $tMin = $points->min('t');
        $tMax = max($points->max('t'), $prev->max('t') ?? 0);
        $valeurs = $points->pluck('v')->merge($prev->pluck('v'))->push($capteur->seuil_min, $capteur->seuil_max);
        $marge = ($capteur->seuil_max - $capteur->seuil_min) * 0.2;
        $vMin = $valeurs->min() - $marge;
        if ($capteur->type_mesure !== 'temperature') {
            $vMin = max(0, $vMin);
        }
        $vMax = $valeurs->max() + $marge;

        $x = fn ($t) => round($g + ($t - $tMin) / max($tMax - $tMin, 1) * ($l - $g - $d), 1);
        $y = fn ($v) => round($haut + (1 - ($v - $vMin) / max($vMax - $vMin, 0.01)) * ($h - $haut - $bas), 1);

        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='{$l}' height='{$h}' viewBox='0 0 {$l} {$h}' font-family='DejaVu Sans' font-size='10'>";

        // Plage normale (vert) et seuils
        $ySmax = $y($capteur->seuil_max); $ySmin = $y($capteur->seuil_min);
        $svg .= "<rect x='{$g}' y='{$ySmax}' width='" . ($l - $g - $d) . "' height='" . ($ySmin - $ySmax) . "' fill='#2fb344' fill-opacity='0.10'/>";
        $svg .= "<line x1='{$g}' y1='{$ySmax}' x2='" . ($l - $d) . "' y2='{$ySmax}' stroke='#d63939' stroke-dasharray='4 3'/>";
        $svg .= "<line x1='{$g}' y1='{$ySmin}' x2='" . ($l - $d) . "' y2='{$ySmin}' stroke='#2fb344' stroke-dasharray='4 3'/>";

        // Axe vertical : 5 graduations
        for ($i = 0; $i <= 4; $i++) {
            $v = $vMin + ($vMax - $vMin) * $i / 4;
            $yy = $y($v);
            $svg .= "<line x1='{$g}' y1='{$yy}' x2='" . ($l - $d) . "' y2='{$yy}' stroke='#e9ecef'/>";
            $svg .= "<text x='" . ($g - 5) . "' y='" . ($yy + 3) . "' text-anchor='end' fill='#667382'>" . Capteur::nombre($v) . "</text>";
        }
        $svg .= "<text x='5' y='12' fill='#667382'>{$capteur->unite}</text>";

        // Axe horizontal : 4 dates
        for ($i = 0; $i <= 3; $i++) {
            $t = $tMin + ($tMax - $tMin) * $i / 3;
            $svg .= "<text x='" . $x($t) . "' y='" . ($h - 10) . "' text-anchor='middle' fill='#667382'>" . date('d/m H\hi', (int) $t) . "</text>";
        }

        // Courbe des mesures
        $ligne = $points->map(fn ($p) => $x($p['t']) . ',' . $y($p['v']))->join(' ');
        $svg .= "<polyline points='{$ligne}' fill='none' stroke='#066fd1' stroke-width='1.8'/>";

        // Prévision en pointillés violets
        if ($prev->isNotEmpty()) {
            $dernier = $points->last();
            $ligneP = collect([$dernier])->merge($prev)->map(fn ($p) => $x($p['t']) . ',' . $y($p['v']))->join(' ');
            $svg .= "<polyline points='{$ligneP}' fill='none' stroke='#ae3ec9' stroke-width='1.5' stroke-dasharray='5 4'/>";
        }

        // Points : rouge = hors seuil, orange = anomalie IA
        foreach ($points as $p) {
            $couleur = $p['hs'] ? '#d63939' : (isset($anomalies[$p['id']]) ? '#f76707' : null);
            if ($couleur) {
                $svg .= "<circle cx='" . $x($p['t']) . "' cy='" . $y($p['v']) . "' r='3.5' fill='{$couleur}'/>";
            }
        }

        return $svg . '</svg>';
    }
}