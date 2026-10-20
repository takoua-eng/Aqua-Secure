<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CoupureRequest;
use App\Models\CoupureProgrammee;
use App\Models\ReseauEau;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\CoupureProgrammeeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class CoupureController extends Controller
{
    public function index(Request $request)
    {
        $coupures = CoupureProgrammee::with([
                'reseau.zone',
                'auteur',
                // Commentaires affichés dans le panneau "Voir détails" de chaque coupure
                'commentaires' => fn ($q) => $q->with('auteur')->withCount('likes')->latest(),
            ])
            ->withCount('commentaires')
            ->when($request->zone, fn ($q, $zone) => $q->dansZone((int) $zone))
            ->when($request->statut, function ($q, $statut) {
                match ($statut) {
                    'a_venir'  => $q->whereNull('annulee_le')->where('debut', '>', now()),
                    'en_cours' => $q->whereNull('annulee_le')->where('debut', '<=', now())->where('fin', '>', now()),
                    'terminee' => $q->whereNull('annulee_le')->where('fin', '<=', now()),
                    'annulee'  => $q->whereNotNull('annulee_le'),
                    default    => null,
                };
            })
            ->orderByDesc('debut')
            ->paginate(15)
            ->withQueryString();

        return view('admin.coupures.index', [
            'coupures' => $coupures,
            'zones'    => Zone::orderBy('commune')->orderBy('nom')->get()->mapWithKeys(fn ($z) => [$z->id => $z->libelle()])->all(),
        ]);
    }

    public function create()
    {
        return view('admin.coupures.create', [
            'coupure' => new CoupureProgrammee(),
            'reseaux' => $this->optionsReseaux(),
        ]);
    }

    public function store(CoupureRequest $request)
    {
        $coupure = CoupureProgrammee::create($request->validated() + ['user_id' => $request->user()->id]);
        $prevenus = $this->prevenir($coupure, 'planifiee');

        return redirect()->route('admin.coupures.index')
            ->with('success', "Coupure programmée. {$prevenus} citoyen(s) de la zone prévenu(s).");
    }

    public function edit(CoupureProgrammee $coupure)
    {
        if (! $coupure->estModifiable()) {
            return redirect()->route('admin.coupures.index')
                ->withErrors(['coupure' => 'Seule une coupure qui n\'a pas encore commencé peut être modifiée.']);
        }

        return view('admin.coupures.edit', ['coupure' => $coupure, 'reseaux' => $this->optionsReseaux()]);
    }

    public function update(CoupureRequest $request, CoupureProgrammee $coupure)
    {
        if (! $coupure->estModifiable()) {
            return redirect()->route('admin.coupures.index')
                ->withErrors(['coupure' => 'Seule une coupure qui n\'a pas encore commencé peut être modifiée.']);
        }

        $coupure->update($request->validated());

        // Les citoyens ne sont prévenus que si les dates ou le réseau changent
        $prevenus = $coupure->wasChanged(['debut', 'fin', 'reseau_eau_id']) ? $this->prevenir($coupure, 'modifiee') : 0;

        return redirect()->route('admin.coupures.index')
            ->with('success', 'Coupure modifiée.' . ($prevenus ? " {$prevenus} citoyen(s) prévenu(s) du changement." : ''));
    }

    // Annuler plutôt que supprimer : la coupure reste dans l'historique et les citoyens sont prévenus
    public function annuler(CoupureProgrammee $coupure)
    {
        if (! in_array($coupure->statut(), ['a_venir', 'en_cours'])) {
            return back()->withErrors(['coupure' => 'Cette coupure est déjà terminée ou annulée.']);
        }

        $coupure->forceFill(['annulee_le' => now()])->save();
        $prevenus = $this->prevenir($coupure, 'annulee');

        return redirect()->route('admin.coupures.index')
            ->with('success', "Coupure annulée. {$prevenus} citoyen(s) prévenu(s).");
    }

    public function destroy(CoupureProgrammee $coupure)
    {
        // Une coupure à venir ou en cours doit d'abord être annulée (pour prévenir les citoyens)
        if (in_array($coupure->statut(), ['a_venir', 'en_cours'])) {
            return back()->withErrors(['coupure' => 'Annulez d\'abord cette coupure : les citoyens concernés seront prévenus.']);
        }

        $coupure->delete();

        return redirect()->route('admin.coupures.index')->with('success', 'Coupure supprimée.');
    }

    // Prévient (email + notification dans l'application) les citoyens vérifiés de la zone ; renvoie leur nombre
    private function prevenir(CoupureProgrammee $coupure, string $evenement): int
    {
        $citoyens = User::where('role', 'citoyen')
            ->whereNotNull('email_verified_at')
            ->where('zone_id', $coupure->reseau()->value('zone_id'))
            ->get();

        Notification::send($citoyens, new CoupureProgrammeeNotification($coupure, $evenement));

        return $citoyens->count();
    }

    // Réseaux groupés par zone pour la liste déroulante : ['Centre-ville — Tunis' => [id => 'CAN-TUN-001 · Canalisation']]
    private function optionsReseaux(): array
    {
        return ReseauEau::with('zone')->orderBy('code')->get()
            ->groupBy(fn ($r) => $r->zone->libelle())
            ->sortKeys()
            ->map(fn ($reseaux) => $reseaux->mapWithKeys(fn ($r) => [$r->id => "{$r->code} · {$r->libelleType()}"])->all())
            ->all();
    }
}
