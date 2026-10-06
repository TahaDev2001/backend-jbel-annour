<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Facture extends Model
{
    protected $connection = 'facturation';

    protected $table = 'apm_depot_facture';

    public $timestamps = false;

    protected $fillable = [
        'numero_facture',
        'date_facture',
        'ice_fournisseur',
        'numero_bc',
        'numero_bl',
        'date_bl',
        'objet_facture',
        'type_facture',
        'type_document',
        'montant_ht',
        'montant_tva',
        'montant_ttc',
        'devise',
        'conditions_paiement',
        'date_echeance',
        'nature_operation',
        'nature_autres_detail',
        'doc_facture_pdf',
        'doc_bon_commande',
        'doc_attestation_fisc',
        'doc_rib',
        'notes',
        'declaration_acceptee',
    ];

    protected $appends = ['bc_files'];

    protected $casts = [
        'montant_ht' => 'float',
        'montant_tva' => 'float',
        'montant_ttc' => 'float',
        'declaration_acceptee' => 'boolean',
    ];

    public const NATURES = [
        'matieres_premieres' => 'Matières premières',
        'pieces_rechange' => 'Pièces de rechange',
        'services_industriels' => 'Services industriels',
        'transport' => 'Transport',
        'location' => 'Location',
        'travaux' => 'Travaux',
        'autres' => 'Autres',
    ];

    public function getBcFilesAttribute(): array
    {
        return self::parseDocList($this->attributes['doc_bon_commande'] ?? null);
    }

    public static function parseDocList(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [];
        }

        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn ($v) => $v !== null && $v !== ''));
            }
        }

        return [$raw];
    }
}