<?php

namespace App\Http\Controllers\Admin\Renovation;

use App\Http\Controllers\Controller;
use App\Models\Financement;
use App\Models\ProjetRenovation;
use App\Services\Renovation\EquiteTerritoriale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/*
 * Binôme 3 — échanges Excel des financements, au format de l'équipe (ReseauImportController) :
 * CSV avec séparateur « ; » et BOM UTF-8, qu'Excel en français ouvre directement.
 *   - export : la liste filtrée des financements (mêmes filtres que la page) ;
 *   - import : chaque ligne est contrôlée comme le formulaire ; une seule erreur => rien n'est importé.
 * Aucune table créée ni modifiée : l'import ajoute seulement des lignes dans « financements ».
 */
class EchangeFinancementsController extends Controller
{
    public const COLONNES = ['projet_id', 'projet', 'source', 'montant'];

    public const LIGNES_MAX = 500;

    public function export(Request $request)
    {
        Gate::authorize('viewAny', Financement::class);

        $financements = Financement::query()
            ->with(['projet.reseau.zone', 'simulation:id,nom'])
            ->when($request->source, fn ($q, $s) => $q->where('source', $s))
            ->when($request->projet, fn ($q, $p) => $q->where('projet_id', $p))
            ->when($request->q, fn ($q, $t) => $q->whereHas('projet', fn ($p) => $p->where('titre', 'like', "%{$t}%")))
            ->when(is_numeric($request->min), fn ($q) => $q->where('montant', '>=', (float) $request->min))
            ->when(is_numeric($request->max), fn ($q) => $q->where('montant', '<=', (float) $request->max))
            ->when($request->origine === 'scenario', fn ($q) => $q->whereNotNull('simulation_id'))
            ->when($request->origine === 'manuel', fn ($q) => $q->whereNull('simulation_id'))
            ->orderBy('id')
            ->get();

        $lignes = [['id', 'date', 'projet_id', 'projet', 'reseau', 'commune', 'gouvernorat', 'statut_projet', 'source', 'source_libelle', 'montant', 'origine']];
        foreach ($financements as $f) {
            $commune = $f->projet?->reseau?->zone?->commune;
            $lignes[] = [
                $f->id, $f->created_at->format('d/m/Y'), $f->projet_id, $f->projet?->titre, $f->projet?->reseau?->code, $commune,
                EquiteTerritoriale::gouvernoratDe($commune), $f->projet?->libelleStatut(), $f->source, $f->libelleSource(),
                number_format((float) $f->montant, 2, ',', ''), $f->simulation ? 'Scénario : ' . $f->simulation->nom : 'Saisie manuelle',
            ];
        }

        return $this->csv($lignes, 'financements-' . now()->format('Y-m-d') . '.csv');
    }

    public function modele()
    {
        Gate::authorize('create', Financement::class);

        $projet = ProjetRenovation::where('statut', '!=', 'termine')->orderBy('id')->first();

        return $this->csv([
            self::COLONNES,
            [$projet?->id ?? 1, $projet?->titre ?? 'Titre du projet (facultatif)', 'Etat', '150000,00'],
            [$projet?->id ?? 1, $projet?->titre ?? 'Titre du projet (facultatif)', 'bailleur', '25000'],
        ], 'modele-import-financements.csv');
    }

    public function formulaire()
    {
        Gate::authorize('create', Financement::class);

        return view('admin.renovation.financements.import', [
            'sources' => Financement::SOURCES,
        ]);
    }

    public function importer(Request $request)
    {
        Gate::authorize('create', Financement::class);

        $request->validate(['fichier' => ['required', 'file', 'max:2048', 'mimes:csv,txt']], [
            'fichier.required' => 'Choisissez un fichier CSV.',
            'fichier.mimes'    => 'Le fichier doit être un CSV (Excel : « Enregistrer sous » > CSV UTF-8).',
            'fichier.max'      => 'Le fichier ne peut pas dépasser 2 Mo.',
        ]);

        $texte = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($request->file('fichier')->getRealPath()));
        if (! mb_check_encoding($texte, 'UTF-8')) {
            $texte = mb_convert_encoding($texte, 'UTF-8', 'Windows-1252');
        }
        $lignes = array_values(array_filter(preg_split('/\r\n|\r|\n/', $texte), fn ($l) => trim($l) !== ''));

        if (count($lignes) < 2) {
            return back()->withErrors(['fichier' => 'Le fichier est vide : il faut une ligne d\'en-tête et au moins une ligne de financement.']);
        }
        if (count($lignes) - 1 > self::LIGNES_MAX) {
            return back()->withErrors(['fichier' => 'Au plus ' . self::LIGNES_MAX . ' financements par import.']);
        }

        $separateur = substr_count($lignes[0], ';') >= substr_count($lignes[0], ',') ? ';' : ',';
        $entetes = array_map(fn ($e) => Str::slug(trim($e), '_'), str_getcsv($lignes[0], $separateur));
        $manquantes = array_diff(['projet_id', 'source', 'montant'], $entetes);
        if ($manquantes) {
            return back()->withErrors(['fichier' => 'Colonnes manquantes : ' . implode(', ', $manquantes) . '. Utilisez le fichier modèle.']);
        }

        [$aCreer, $erreurs] = $this->controler(array_slice($lignes, 1), $entetes, $separateur, $request->user()->isAdmin());

        if ($erreurs) {
            return back()->with('erreurs_import', $erreurs)
                ->withErrors(['fichier' => count($erreurs) . ' ligne(s) en erreur : aucun financement n\'a été importé. Corrigez le fichier puis recommencez.']);
        }

        DB::transaction(fn () => collect($aCreer)->each(fn ($f) => Financement::create($f)));

        return redirect()->route('admin.financements.index')
            ->with('success', count($aCreer) . ' financement(s) importé(s) pour un total de '
                . number_format(array_sum(array_column($aCreer, 'montant')), 2, ',', ' ') . ' DT.');
    }

    /**
     * Mêmes règles que le formulaire (FinancementRequest), plus le cumul des lignes du fichier
     * pour le plafond du coût déclaré.
     *
     * @return array{0: list<array>, 1: list<string>}
     */
    private function controler(array $lignes, array $entetes, string $separateur, bool $admin): array
    {
        $projets = ProjetRenovation::withSum('financements', 'montant')->get()->keyBy('id');
        // Source acceptée par son code (« Etat ») ou son libellé (« État », « Bailleur de fonds »), sans tenir compte de la casse
        $sources = [];
        foreach (Financement::SOURCES as $cle => $libelle) {
            $sources[mb_strtolower($cle)] = $cle;
            $sources[mb_strtolower($libelle)] = $cle;
        }
        $cumul = [];
        $aCreer = [];
        $erreurs = [];

        foreach ($lignes as $i => $ligne) {
            $numero = $i + 2;
            $valeurs = array_pad(str_getcsv($ligne, $separateur), count($entetes), '');
            $v = array_combine($entetes, array_slice($valeurs, 0, count($entetes)));
            $id = trim((string) ($v['projet_id'] ?? ''));
            $projet = ctype_digit($id) ? $projets->get((int) $id) : null;

            $source = $sources[mb_strtolower(trim((string) ($v['source'] ?? '')))] ?? null;

            $brut = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim((string) ($v['montant'] ?? '')));
            $brut = str_replace(',', '.', $brut);
            $montant = preg_match('/^\d+(\.\d{1,2})?$/', $brut) ? (float) $brut : null;

            $probleme = match (true) {
                ! $projet                         => "projet « {$id} » introuvable",
                ! $source                         => 'source « ' . ($v['source'] ?? '') . ' » inconnue (Etat, municipalite, bailleur ou don)',
                $montant === null                 => 'montant « ' . ($v['montant'] ?? '') . ' » invalide (nombre positif, 2 décimales au plus)',
                $montant <= 0                     => 'le montant doit être strictement positif',
                $montant > Financement::MONTANT_MAX => 'montant supérieur à 99 999 999,99 DT',
                $projet->statut === 'termine' && ! $admin => "le projet « {$projet->titre} » est terminé",
                default                           => null,
            };

            if (! $probleme && $projet->cout_estime !== null && (float) $projet->cout_estime > 0) {
                $cumul[$projet->id] = ($cumul[$projet->id] ?? $projet->totalFinance()) + $montant;
                if ($cumul[$projet->id] > (float) $projet->cout_estime + 0.005) {
                    $probleme = "le total des financements dépasserait le coût déclaré de « {$projet->titre} » ("
                        . number_format((float) $projet->cout_estime, 2, ',', ' ') . ' DT)';
                }
            }

            if ($probleme) {
                $erreurs[] = "Ligne {$numero} : {$probleme}.";
            } else {
                $aCreer[] = ['projet_id' => $projet->id, 'source' => $source, 'montant' => round($montant, 2)];
            }
        }

        return [$aCreer, $erreurs];
    }

    private function csv(array $lignes, string $nom)
    {
        $echapper = function ($v) {
            $v = (string) $v;
            // Un texte saisi qui commence par = + - @ serait exécuté comme une formule par Excel : on le neutralise
            if (preg_match('/^[=+@\t\r]|^-[^\d]/', $v)) {
                $v = "'" . $v;
            }

            return preg_match('/[;"\r\n]/', $v) ? '"' . str_replace('"', '""', $v) . '"' : $v;
        };
        $contenu = "\u{FEFF}" . collect($lignes)->map(fn ($l) => implode(';', array_map($echapper, $l)))->implode("\r\n") . "\r\n";

        return response($contenu, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $nom . '"',
        ]);
    }
}
