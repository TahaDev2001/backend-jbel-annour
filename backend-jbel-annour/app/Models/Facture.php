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
}
