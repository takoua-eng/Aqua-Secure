<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReseauEauRequest;
use App\Models\ReseauEau;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/*
 * Import de réseaux depuis un fichier CSV (Excel : "Enregistrer sous… CSV").
 * Chaque ligne passe par les mêmes règles que le formulaire (ReseauEauRequest).
 * Tout ou rien : si une seule ligne est invalide, rien n'est importé et chaque erreur est listée.
 */
class ReseauImportController extends Controller
{
    public const COLONNES = ['code', 'zone', 'commune', 'type', 'materiau', 'date_mise_en_service', 'etat', 'latitude', 'longitude'];
    public const OBLIGATOIRES = ['code', 'zone', 'commune', 'type', 'materiau', 'date_mise_en_service', 'etat'];
    public const LIGNES_MAX = 1000;

    public function formulaire()
    {
        return view('admin.reseaux.import');
    }

    // Fichier modèle à remplir (séparateur ";" et BOM UTF-8 pour qu'Excel affiche bien les accents)
    public function modele()
    {
        $zone = Zone::orderBy('commune')->orderBy('nom')->first();
        $lignes = [
            self::COLONNES,
            ['CAN-EX-001', $zone->nom ?? 'Centre-ville', $zone->commune ?? 'Tunis', 'canalisation', 'fonte', '15/03/1985', 'degrade', '36.8001', '10.1797'],
            ['RES-EX-001', $zone->nom ?? 'Centre-ville', $zone->commune ?? 'Tunis', 'Réservoir', 'Béton', '2004-06-01', 'Bon', '', ''],
        ];

        $contenu = "\u{FEFF}" . collect($lignes)->map(fn ($l) => implode(';', $l))->implode("\r\n") . "\r\n";

        return response($contenu, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele-import-reseaux.csv"',
        ]);
    }

    public function importer(Request $request)
    {
        $request->validate([
            'fichier' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'fichier.required' => 'Choisissez un fichier CSV.',
            'fichier.file'     => "Le fichier n'a pas pu être envoyé.",
            'fichier.mimes'    => 'Le fichier doit être au format CSV (.csv).',
            'fichier.max'      => 'Le fichier ne doit pas dépasser 2 Mo.',
        ]);

        // Lecture : BOM retiré, Windows-1252 converti en UTF-8, lignes vides ignorées
        $texte = file_get_contents($request->file('fichier')->getRealPath());
        $texte = preg_replace('/^\xEF\xBB\xBF/', '', $texte);
        if (! mb_check_encoding($texte, 'UTF-8')) {
            $texte = mb_convert_encoding($texte, 'UTF-8', 'Windows-1252');
        }
        $lignes = array_values(array_filter(preg_split('/\r\n|\r|\n/', $texte), fn ($l) => trim($l) !== ''));

        if (count($lignes) < 2) {
            return back()->withErrors(['fichier' => "Le fichier est vide : il faut une ligne d'en-tête et au moins une ligne de données."]);
        }
        if (count($lignes) - 1 > self::LIGNES_MAX) {
            return back()->withErrors(['fichier' => 'Au plus ' . self::LIGNES_MAX . ' lignes par import.']);
        }

        // Séparateur : ";" (Excel en français) ou ","
        $separateur = substr_count($lignes[0], ';') >= substr_count($lignes[0], ',') ? ';' : ',';
        $entetes = array_map(fn ($e) => Str::slug(trim($e), '_'), str_getcsv($lignes[0], $separateur));

        $manquantes = array_diff(self::OBLIGATOIRES, $entetes);
        if ($manquantes) {
            return back()->withErrors(['fichier' => 'Colonnes manquantes : ' . implode(', ', $manquantes) . '. Utilisez le fichier modèle.']);
        }

        $erreurs = [];
        $aCreer = [];
        $codesVus = [];

        foreach (array_slice($lignes, 1) as $i => $ligne) {
            $numero = $i + 2; // numéro de ligne tel qu'affiché dans Excel
            $valeurs = str_getcsv($ligne, $separateur);
            $brut = [];
            foreach ($entetes as $j => $entete) {
                $brut[$entete] = trim($valeurs[$j] ?? '');
            }

            $zone = Zone::where('nom', $brut['zone'])->where('commune', $brut['commune'])->first();
            if (! $zone) {
                $erreurs[] = "Ligne {$numero} : la zone « {$brut['zone']} — {$brut['commune']} » n'existe pas (créez-la d'abord).";
                continue;
            }

            $donnees = [
                'zone_id'   => $zone->id,
                'code'      => mb_strtoupper($brut['code']),
                'type'      => self::choix($brut['type'], ReseauEau::TYPES),
                'materiau'  => self::choix($brut['materiau'], ReseauEau::MATERIAUX),
                'date_mise_en_service' => self::date($brut['date_mise_en_service']),
                'etat'      => self::choix($brut['etat'], ReseauEau::ETATS),
                'latitude'  => self::nombre($brut['latitude'] ?? ''),
                'longitude' => self::nombre($brut['longitude'] ?? ''),
            ];

            // Mêmes règles que le formulaire (code unique, listes, dates, coordonnées…)
            $validation = Validator::make($donnees, ReseauEauRequest::regles(), ReseauEauRequest::messagesErreur());
            foreach ($validation->errors()->all() as $message) {
                $erreurs[] = "Ligne {$numero} : {$message}";
            }

            if (isset($codesVus[$donnees['code']])) {
                $erreurs[] = "Ligne {$numero} : le code {$donnees['code']} apparaît déjà ligne {$codesVus[$donnees['code']]}.";
            }
            $codesVus[$donnees['code']] = $numero;

            $aCreer[] = $donnees;
        }

        if ($erreurs) {
            return back()->with('erreursImport', $erreurs);
        }

        // Tout est valide : on crée tout d'un coup (l'historique "création" est ajouté par le modèle)
        DB::transaction(fn () => collect($aCreer)->each(fn ($d) => ReseauEau::create($d)));

        return redirect()->route('admin.reseaux.index')->with('success', count($aCreer) . ' réseau(x) importé(s).');
    }

    // "Réservoir", "reservoir", "RESERVOIR" => "reservoir" ; "Hors service" => "hors_service"
    private static function choix(string $valeur, array $liste): string
    {
        $cle = Str::slug($valeur, '_');
        if (isset($liste[$cle])) {
            return $cle;
        }
        foreach ($liste as $code => $libelle) {
            if (Str::slug($libelle, '_') === $cle) {
                return $code;
            }
        }

        return $valeur; // valeur inconnue : la validation renverra le bon message
    }

    // Accepte 2004-06-01 et 01/06/2004
    private static function date(string $valeur): string
    {
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $valeur, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return $valeur;
    }

    // "36,8001" (virgule décimale d'Excel) => "36.8001" ; vide => null
    private static function nombre(string $valeur): ?string
    {
        return $valeur === '' ? null : str_replace(',', '.', $valeur);
    }
}
