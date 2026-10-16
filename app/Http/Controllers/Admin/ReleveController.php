<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReleveRequest;
use App\Models\Capteur;
use App\Models\Releve;
use App\Services\EnregistrementReleve;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReleveController extends Controller
{
    public function index(Request $request)
    {
        $releves = $this->requeteFiltree($request)
            ->with('capteur.reseau')
            ->orderByDesc('date_releve')
            ->paginate(20)
            ->withQueryString();

        return view('admin.releves.index', [
            'releves'  => $releves,
            'capteurs' => Capteur::orderBy('code')->get()->mapWithKeys(fn (Capteur $c) => [$c->id => $c->libelle()])->all(),
        ]);
    }

    // Export CSV des relevés filtrés (ouvrable dans Excel)
    public function export(Request $request)
    {
        $nomFichier = 'releves-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($request) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");   // BOM : Excel affiche correctement les accents
            fputcsv($sortie, ['Date', 'Capteur', 'Mesure', 'Réseau', 'Valeur', 'Unité', 'Seuil min', 'Seuil max', 'État', 'Remarque'], ';');

            $this->requeteFiltree($request)
                ->with('capteur.reseau')
                ->orderByDesc('date_releve')
                ->chunk(500, function ($releves) use ($sortie) {
                    foreach ($releves as $r) {
                        fputcsv($sortie, [
                            $r->date_releve->format('d/m/Y H:i'),
                            $r->capteur->code,
                            $r->capteur->libelleTypeMesure(),
                            $r->capteur->reseau->code,
                            str_replace('.', ',', (string) $r->valeur),
                            $r->capteur->unite,
                            str_replace('.', ',', (string) $r->capteur->seuil_min),
                            str_replace('.', ',', (string) $r->capteur->seuil_max),
                            $r->hors_seuil ? 'Hors seuil (' . $r->sens() . ')' : 'Normal',
                            $r->remarque,
                        ], ';');
                    }
                });

            fclose($sortie);
        }, $nomFichier, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(Request $request)
    {
        return view('admin.releves.create', [
            // ?capteur=ID permet de pré-remplir le capteur (bouton depuis la fiche capteur)
            'releve' => new Releve([
                'capteur_id'  => $request->integer('capteur') ?: null,
                'date_releve' => now(),
            ]),
            ...$this->donneesFormulaire(),
        ]);
    }

    // ReleveRequest a déjà tout vérifié. EnregistrementReleve crée le relevé (hors_seuil calculé par le modèle),
    // détecte une anomalie et envoie l'alerte email aux gestionnaires si besoin.
    public function store(ReleveRequest $request)
    {
        $donnees = $request->validated();
        $resultat = EnregistrementReleve::enregistrer(
            Capteur::findOrFail($donnees['capteur_id']),
            (float) $donnees['valeur'],
            $donnees['date_releve'],
            $donnees['remarque'] ?? null,
        );

        $message = $this->message('Relevé enregistré.', $resultat['releve']);
        if ($resultat['alerte']) {
            $message .= ' Une alerte email a été envoyée aux gestionnaires.';
        }

        return redirect()->route('admin.capteurs.show', $resultat['releve']->capteur_id)
            ->with('success', $message);
    }

    public function edit(Releve $releve)
    {
        return view('admin.releves.edit', [
            'releve' => $releve,
            ...$this->donneesFormulaire($releve),
        ]);
    }

    public function update(ReleveRequest $request, Releve $releve)
    {
        $releve->update($request->validated());

        return redirect()->route('admin.capteurs.show', $releve->capteur_id)
            ->with('success', $this->message('Relevé modifié.', $releve));
    }

    public function destroy(Releve $releve)
    {
        $releve->delete();

        return back()->with('success', 'Relevé supprimé.');
    }

    // Filtres communs à la liste et à l'export
    private function requeteFiltree(Request $request): Builder
    {
        return Releve::query()
            ->when($request->capteur, fn ($query, $capteur) => $query->where('capteur_id', $capteur))
            ->when($request->etat === 'hors_seuil', fn ($query) => $query->where('hors_seuil', true))
            ->when($request->etat === 'normal', fn ($query) => $query->where('hors_seuil', false))
            ->when($request->du, fn ($query, $du) => $query->whereDate('date_releve', '>=', $du))
            ->when($request->au, fn ($query, $au) => $query->whereDate('date_releve', '<=', $au));
    }

    // Capteurs utilisables + leurs seuils (pour l'aide affichée en direct dans le formulaire)
    private function donneesFormulaire(?Releve $releve = null): array
    {
        $capteurs = Capteur::query()
            // Un capteur hors service n'apparaît pas, sauf s'il s'agit du capteur du relevé modifié
            ->where(fn ($q) => $q->where('statut', '!=', 'hors_service')
                ->when($releve, fn ($q) => $q->orWhere('id', $releve->capteur_id)))
            ->orderBy('code')
            ->get();

        return [
            'capteurs' => $capteurs->mapWithKeys(fn (Capteur $c) => [$c->id => $c->libelle()])->all(),
            'infosCapteurs' => $capteurs->mapWithKeys(fn (Capteur $c) => [$c->id => [
                'unite' => $c->unite,
                'min'   => $c->seuil_min,
                'max'   => $c->seuil_max,
                'plage' => $c->plageSeuils(),
                'type'   => $c->libelleTypeMesure(),
                'limMin' => Capteur::LIMITES[$c->type_mesure][0],
                'limMax' => Capteur::LIMITES[$c->type_mesure][1],
            ]])->all(),
        ];
    }

    // Message après enregistrement : alerte hors seuil + alerte anomalie détectée par l'IA
    private function message(string $texte, Releve $releve): string
    {
        $releve->load('capteur');
        $capteur = $releve->capteur;

        if ($releve->hors_seuil) {
            $texte .= ' Attention : ' . $capteur->avecUnite($releve->valeur)
                . " est {$releve->sens()} (seuils normaux : {$capteur->plageSeuils()}).";
        }

        // Le nouveau relevé est-il anormal par rapport aux précédents ?
        $anomalie = EnregistrementReleve::anomalie($releve);

        if ($anomalie) {
            $texte .= ' Analyse IA : valeur inhabituelle (attendue vers '
                . $capteur->avecUnite($anomalie['attendu']) . ', score z = ' . $anomalie['score'] . ').';
        }

        return $texte;
    }
}