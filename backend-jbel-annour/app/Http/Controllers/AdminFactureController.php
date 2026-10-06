<?php

namespace App\Http\Controllers;

use App\Models\Facture;
use App\Services\XlsxExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class AdminFactureController extends Controller
{
    private const TEXT_FIELDS = [
        'numero_facture', 'date_facture', 'numero_bc', 'numero_bl', 'date_bl',
        'ice_fournisseur', 'objet_facture', 'type_document', 'devise',
        'conditions_paiement', 'date_echeance', 'nature_operation',
        'nature_autres_detail', 'notes',
    ];

    private const NUM_FIELDS = ['montant_ht', 'montant_tva', 'montant_ttc'];

    private const DOC_FIELDS = ['doc_facture_pdf', 'doc_attestation_fisc', 'doc_rib'];

    private const EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];

    // GET /api/admin/factures?search=&depuis=&jusqu=&nature=&page=
    public function index(Request $request)
    {
        $stats = Facture::query()
            ->selectRaw("COUNT(*) AS total_factures,
                COALESCE(SUM(montant_ttc),0) AS total_ttc,
                COALESCE(SUM(declaration_acceptee),0) AS acceptees,
                COALESCE(SUM(CASE WHEN doc_facture_pdf IS NOT NULL AND doc_facture_pdf <> '' THEN 1 ELSE 0 END),0) AS avec_docs")
            ->toBase()
            ->first();

        $page = $this->filtered($request)
            ->orderByDesc('date_facture')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'total_pages' => max($page->lastPage(), 1),
                'per_page' => 20,
            ],
            'stats' => [
                'total_factures' => (int) $stats->total_factures,
                'total_ttc' => (float) $stats->total_ttc,
                'acceptees' => (int) $stats->acceptees,
                'avec_docs' => (int) $stats->avec_docs,
            ],
            'natures' => Facture::NATURES,
        ]);
    }

    // POST /api/admin/factures/{id}  (multipart/form-data)
    public function update(Request $request, int $id)
    {
        $facture = Facture::findOrFail($id);

        $fileRule = 'file|mimes:pdf,png,jpg,jpeg,webp|max:10240';

        $request->validate([
            'date_facture' => 'nullable|date_format:Y-m-d',
            'date_bl' => 'nullable|date_format:Y-m-d',
            'date_echeance' => 'nullable|date_format:Y-m-d',
            'montant_ht' => 'nullable|numeric',
            'montant_tva' => 'nullable|numeric',
            'montant_ttc' => 'nullable|numeric',
            'doc_facture_pdf' => "nullable|{$fileRule}",
            'doc_attestation_fisc' => "nullable|{$fileRule}",
            'doc_rib' => "nullable|{$fileRule}",
            'doc_bon_commande' => 'nullable|array',
            'doc_bon_commande.*' => $fileRule,
        ]);

        $data = [];

        foreach (self::TEXT_FIELDS as $col) {
            if ($request->has($col)) {
                $val = trim((string) $request->input($col));
                $data[$col] = $val === '' ? null : $val;
            }
        }

        foreach (self::NUM_FIELDS as $col) {
            if ($request->has($col)) {
                $data[$col] = (float) $request->input($col);
            }
        }

        if ($request->has('declaration_acceptee')) {
            $data['declaration_acceptee'] = $request->boolean('declaration_acceptee');
        }

        foreach (self::DOC_FIELDS as $col) {
            if ($request->hasFile($col)) {
                $saved = $this->saveDoc($request->file($col), $col);
                if ($saved !== null) {
                    $data[$col] = $saved;
                }
            }
        }

        if ($request->hasFile('doc_bon_commande')) {
            $list = $facture->bc_files;
            foreach ($request->file('doc_bon_commande') as $file) {
                $saved = $this->saveDoc($file, 'doc_bon_commande', count($list));
                if ($saved !== null) {
                    $list[] = $saved;
                }
            }
            $data['doc_bon_commande'] = json_encode(
                array_values($list),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        $facture->update($data);

        return response()->json([
            'message' => 'Facture mise à jour avec succès.',
            'facture' => $facture->fresh(),
        ]);
    }

    // GET /api/admin/factures/download/{filename}
    public function download(string $filename)
    {
        $filename = basename($filename);

        if (! preg_match('/^[A-Za-z0-9._-]+\.(pdf|png|jpe?g|webp)$/i', $filename)) {
            abort(404, 'Fichier introuvable.');
        }

        $path = $this->uploadDir() . DIRECTORY_SEPARATOR . $filename;

        if (! is_file($path)) {
            abort(404, 'Fichier introuvable.');
        }

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // GET /api/admin/factures/export?search=&depuis=&jusqu=&nature=
    public function export(Request $request)
    {
        $headers = [
            'N° Facture', 'Date facture', 'N° BC', 'N° BL', 'Date BL', 'ICE Fournisseur', 'Objet',
            'Type document', 'Montant HT', 'Montant TVA', 'Montant TTC', 'Devise',
            'Conditions paiement', 'Date échéance', 'Nature opération', 'Détail autre nature',
            'Notes', 'Déclaration acceptée',
        ];

        $rows = $this->filtered($request)
            ->orderByDesc('date_facture')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Facture $f) => [
                $f->numero_facture,
                $this->fmtDate($f->date_facture),
                $f->numero_bc,
                $f->numero_bl,
                $this->fmtDate($f->date_bl),
                $f->ice_fournisseur,
                $f->objet_facture,
                $f->type_document,
                (float) $f->montant_ht,
                (float) $f->montant_tva,
                (float) $f->montant_ttc,
                $f->devise ?: 'MAD',
                $f->conditions_paiement,
                $this->fmtDate($f->date_echeance),
                Facture::NATURES[$f->nature_operation] ?? $f->nature_operation,
                $f->nature_autres_detail,
                $f->notes,
                $f->declaration_acceptee ? 'Oui' : 'Non',
            ])
            ->all();

        return XlsxExporter::download(
            $headers,
            $rows,
            [8, 9, 10],
            'factures_export_' . date('Ymd_His') . '.xlsx'
        );
    }

    private function filtered(Request $request): Builder
    {
        $query = Facture::query();

        $search = trim((string) $request->query('search', ''));
        $depuis = trim((string) $request->query('depuis', ''));
        $jusqu = trim((string) $request->query('jusqu', ''));
        $nature = trim((string) $request->query('nature', ''));

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('numero_facture', 'like', "%{$search}%")
                    ->orWhere('numero_bc', 'like', "%{$search}%")
                    ->orWhere('objet_facture', 'like', "%{$search}%");
            });
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $depuis)) {
            $query->where('date_facture', '>=', $depuis);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $jusqu)) {
            $query->where('date_facture', '<=', $jusqu);
        }

        if ($nature !== '') {
            $query->where('nature_operation', $nature);
        }

        return $query;
    }

    private function saveDoc(UploadedFile $file, string $field, ?int $idx = null): ?string
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, self::EXTENSIONS, true)) {
            return null;
        }

        $dir = $this->uploadDir();

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $name = $field
            . ($idx !== null ? "_{$idx}" : '')
            . '_' . date('Ymd_His')
            . '_' . bin2hex(random_bytes(4))
            . '.' . $ext;

        $file->move($dir, $name);

        return $name;
    }

    private function uploadDir(): string
    {
        return public_path('uploads/factures');
    }

    private function fmtDate(?string $date): string
    {
        if (! $date || $date === '0000-00-00') {
            return '—';
        }

        $dt = \DateTime::createFromFormat('Y-m-d', $date);

        return $dt ? $dt->format('d/m/Y') : $date;
    }
}