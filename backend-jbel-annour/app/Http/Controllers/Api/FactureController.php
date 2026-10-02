<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Facture;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class FactureController extends Controller
{
    private const DOCS_SIMPLES = ['doc_facture_pdf', 'doc_attestation_fisc', 'doc_rib'];

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $data = json_decode((string) $request->input('data'), true);

        if (! is_array($data)) {
            throw ValidationException::withMessages([
                'data' => ['Les données du formulaire sont invalides.'],
            ]);
        }

        // Même nettoyage que l'ancien script : on retire les numéros vides.
        $data['numero_bc'] = $this->nettoyerListe($data['numero_bc'] ?? []);
        $data['numero_bl'] = $this->nettoyerListe($data['numero_bl'] ?? []);

        $fichiers = [
            'doc_facture_pdf' => $request->file('doc_facture_pdf'),
            'doc_attestation_fisc' => $request->file('doc_attestation_fisc'),
            'doc_rib' => $request->file('doc_rib'),
            'doc_bon_commande' => array_values(array_filter((array) $request->file('doc_bon_commande', []))),
        ];

        $v = $this->valider($data, $fichiers);

        // Enregistrement des fichiers dans public/uploads/factures (seuls les noms vont en base).
        $docs = [];
        foreach (self::DOCS_SIMPLES as $champ) {
            if ($fichiers[$champ]) {
                $docs[$champ] = $this->enregistrerFichier($fichiers[$champ], $champ);
            }
        }
        $docsBonCommande = [];
        foreach ($fichiers['doc_bon_commande'] as $i => $fichier) {
            $docsBonCommande[] = $this->enregistrerFichier($fichier, "doc_bon_commande_{$i}");
        }

        $valeurs = [
            'numero_facture' => $v['numero_facture'],
            'date_facture' => $v['date_facture'],
            'ice_fournisseur' => $v['ice_fournisseur'],
            'numero_bc' => implode(', ', $v['numero_bc']),
            'numero_bl' => implode(', ', $v['numero_bl']),
            'date_bl' => $v['date_bl'],
            'objet_facture' => $this->ouNull($v['objet_facture'] ?? null),
            'type_facture' => $v['type_facture'],
            'type_document' => $v['type_document'],
            'montant_ht' => $this->montant($v['montant_ht'] ?? null),
            'montant_tva' => $this->montant($v['montant_tva'] ?? null),
            'montant_ttc' => $this->montant($v['montant_ttc'] ?? null),
            'devise' => $this->ouNull($v['devise'] ?? null) ?? 'MAD',
            'conditions_paiement' => $this->ouNull($v['conditions_paiement'] ?? null),
            'date_echeance' => $this->ouNull($v['date_echeance'] ?? null),
            'nature_operation' => $this->ouNull($v['nature_operation'] ?? null) ?? 'autres',
            'nature_autres_detail' => $this->ouNull($v['nature_autres_detail'] ?? null),
            'notes' => $this->ouNull($v['notes'] ?? null),
            'declaration_acceptee' => 1,
        ];

        try {
            $facture = Facture::create($valeurs + [
                'doc_facture_pdf' => $docs['doc_facture_pdf'] ?? null,
                'doc_bon_commande' => $docsBonCommande ? json_encode($docsBonCommande, JSON_UNESCAPED_UNICODE) : null,
                'doc_attestation_fisc' => $docs['doc_attestation_fisc'] ?? null,
                'doc_rib' => $docs['doc_rib'] ?? null,
            ]);
        } catch (QueryException $e) {
            $this->supprimerFichiers(array_merge(array_values($docs), $docsBonCommande));

            if (($e->errorInfo[1] ?? null) === 1062) {
                throw ValidationException::withMessages([
                    'numero_facture' => ["Ce numéro de facture ({$v['numero_facture']}) existe déjà. Veuillez vérifier le numéro saisi."],
                ]);
            }

            throw $e;
        }

        // Relais vers l'API externe : hors transaction, un échec ne remet pas en cause l'enregistrement.
        $this->relayer($valeurs, $docs, $docsBonCommande);

        return response()->json([
            'message' => 'Votre facture a été enregistrée avec succès. Elle sera traitée après vérification de conformité.',
            'facture' => $facture,
        ], 201);
    }

    /**
     * Valide les données JSON et les fichiers envoyés.
     */
    private function valider(array $data, array $fichiers): array
    {
        $images = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240';

        return Validator::make($data + $fichiers, [
            'numero_facture' => 'required|string|max:255',
            'date_facture' => 'required|date',
            'ice_fournisseur' => 'required|string|max:255',
            'numero_bc' => 'required|array|min:1',
            'numero_bc.*' => 'string|max:255',
            'numero_bl' => 'required|array|min:1',
            'numero_bl.*' => 'string|max:255',
            'date_bl' => 'required|date',
            'objet_facture' => 'nullable|string',
            'type_facture' => 'required|in:facture,avoir',
            'type_document' => 'required|in:copie,original',
            'montant_ht' => 'nullable|numeric',
            'montant_tva' => 'nullable|numeric',
            'montant_ttc' => 'nullable|numeric',
            'devise' => 'nullable|string|max:10',
            'conditions_paiement' => 'nullable|string',
            'date_echeance' => 'nullable|date',
            'nature_operation' => 'nullable|string',
            'nature_autres_detail' => 'nullable|string',
            'notes' => 'nullable|string',
            'declaration_acceptee' => 'accepted',
            'doc_facture_pdf' => 'nullable|file|mimes:pdf|max:10240',
            'doc_attestation_fisc' => $images,
            'doc_rib' => $images,
            'doc_bon_commande' => 'array',
            'doc_bon_commande.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'numero_facture.required' => 'Veuillez renseigner le numéro de facture.',
            'date_facture.required' => 'Veuillez renseigner la date de facture.',
            'date_facture.date' => "La date de facture n'est pas valide.",
            'ice_fournisseur.required' => 'Veuillez renseigner le numéro ICE du fournisseur.',
            'numero_bc.required' => 'Veuillez renseigner au moins un numéro de bon de commande.',
            'numero_bc.min' => 'Veuillez renseigner au moins un numéro de bon de commande.',
            'numero_bl.required' => 'Veuillez renseigner au moins un numéro de bon de livraison.',
            'numero_bl.min' => 'Veuillez renseigner au moins un numéro de bon de livraison.',
            'date_bl.required' => 'Veuillez renseigner la date du bon de livraison.',
            'date_bl.date' => "La date du bon de livraison n'est pas valide.",
            'date_echeance.date' => "La date d'échéance n'est pas valide.",
            'type_facture.required' => "Veuillez préciser s'il s'agit d'une facture ou d'une facture avoir.",
            'type_facture.in' => "Veuillez préciser s'il s'agit d'une facture ou d'une facture avoir.",
            'type_document.required' => 'Veuillez préciser si le document est une copie ou un original.',
            'type_document.in' => 'Veuillez préciser si le document est une copie ou un original.',
            'montant_ht.numeric' => 'Le montant HT doit être un nombre.',
            'montant_tva.numeric' => 'Le montant TVA doit être un nombre.',
            'montant_ttc.numeric' => 'Le montant TTC doit être un nombre.',
            'declaration_acceptee.accepted' => 'Veuillez accepter la déclaration pour soumettre votre dépôt.',
            'doc_facture_pdf.mimes' => 'Format non autorisé pour la facture (PDF attendu).',
            'doc_facture_pdf.max' => 'La facture est trop volumineuse (max 10 Mo).',
            'doc_attestation_fisc.mimes' => "Format non autorisé pour l'attestation fiscale (PDF, JPG ou PNG attendu).",
            'doc_attestation_fisc.max' => "L'attestation fiscale est trop volumineuse (max 10 Mo).",
            'doc_rib.mimes' => 'Format non autorisé pour le RIB (PDF, JPG ou PNG attendu).',
            'doc_rib.max' => 'Le RIB est trop volumineux (max 10 Mo).',
            'doc_bon_commande.*.mimes' => 'Format non autorisé pour un bon de commande (PDF, JPG ou PNG attendu).',
            'doc_bon_commande.*.max' => 'Un bon de commande est trop volumineux (max 10 Mo).',
            'uploaded' => "Le fichier n'a pas pu être téléchargé. Veuillez réessayer.",
            'file' => 'Le fichier envoyé est invalide.',
            'string' => 'Le champ :attribute doit être un texte.',
            'max' => 'Le champ :attribute est trop long.',
        ])->validate();
    }

    /**
     * Trim + suppression des valeurs vides (comme array_filter(array_map('trim', ...)) de l'ancien script).
     */
    private function nettoyerListe(mixed $valeurs): array
    {
        $valeurs = is_array($valeurs) ? $valeurs : [$valeurs];

        return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $valeurs), fn ($v) => $v !== ''));
    }

    private function ouNull(mixed $valeur): ?string
    {
        $valeur = trim((string) $valeur);

        return $valeur === '' ? null : $valeur;
    }

    private function montant(mixed $valeur): float
    {
        return is_numeric($valeur) ? (float) $valeur : 0.0;
    }

    /**
     * Enregistre un fichier dans public/uploads/factures et retourne son nom (même format que l'ancien script).
     */
    private function enregistrerFichier(UploadedFile $fichier, string $prefixe): string
    {
        $ext = strtolower($fichier->getClientOriginalExtension());
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        $nom = $prefixe.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;

        File::ensureDirectoryExists($this->dossier());
        $fichier->move($this->dossier(), $nom);

        return $nom;
    }

    private function supprimerFichiers(array $noms): void
    {
        foreach ($noms as $nom) {
            File::delete($this->dossier().DIRECTORY_SEPARATOR.$nom);
        }
    }

    private function dossier(): string
    {
        return public_path('uploads/factures');
    }

    /**
     * Relaie la facture vers l'API externe. Un échec est journalisé sans bloquer la réponse.
     */
    private function relayer(array $valeurs, array $docs, array $docsBonCommande): void
    {
        $url = config('services.factures_api.url');
        $cle = config('services.factures_api.key');
        $numero = $valeurs['numero_facture'];

        if (empty($cle)) {
            Log::error('Relais facture non effectué : FACTURES_API_KEY absente du .env', ['numero_facture' => $numero]);

            return;
        }

        $data = ['declaration_acceptee' => true] + $valeurs;

        try {
            $requete = Http::withHeaders(['X-API-Key' => $cle])
                ->connectTimeout(10)
                ->timeout(180)
                ->asMultipart();

            foreach ($docs as $champ => $nom) {
                $requete->attach($champ, fopen($this->dossier().DIRECTORY_SEPARATOR.$nom, 'r'), $nom);
            }
            foreach ($docsBonCommande as $i => $nom) {
                $requete->attach("doc_bon_commande[{$i}]", fopen($this->dossier().DIRECTORY_SEPARATOR.$nom, 'r'), $nom);
            }

            $reponse = $requete->post($url, ['data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);

            if ($reponse->failed()) {
                Log::error("Échec du relais de la facture vers l'API externe", [
                    'numero_facture' => $numero,
                    'http_code' => $reponse->status(),
                    'body' => Str::limit($reponse->body(), 1000),
                ]);
            }
        } catch (Throwable $e) {
            Log::error("Échec du relais de la facture vers l'API externe", [
                'numero_facture' => $numero,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
