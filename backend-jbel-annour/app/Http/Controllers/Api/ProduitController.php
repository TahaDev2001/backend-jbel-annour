<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\CommandeProduitConfirmation;
use App\Models\Produit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProduitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json(Produit::latest()->paginate(15));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $quantiteRequise = 'Veuillez renseigner au moins une quantité (B7, B10 ou 10).';

        $validated = $request->validate([
            'quantite_b7' => 'nullable|integer|min:1|required_without_all:quantite_b10,quantite_10',
            'quantite_b10' => 'nullable|integer|min:1|required_without_all:quantite_b7,quantite_10',
            'quantite_10' => 'nullable|integer|min:1|required_without_all:quantite_b7,quantite_b10',
            'societe' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'telephone' => 'required|string|max:20',
        ], [
            'quantite_b7.required_without_all' => $quantiteRequise,
            'quantite_b10.required_without_all' => $quantiteRequise,
            'quantite_10.required_without_all' => $quantiteRequise,
            'quantite_b7.integer' => 'La quantité B7 doit être un nombre entier.',
            'quantite_b10.integer' => 'La quantité B10 doit être un nombre entier.',
            'quantite_10.integer' => 'La quantité 10 doit être un nombre entier.',
            'quantite_b7.min' => 'La quantité B7 doit être au moins 1.',
            'quantite_b10.min' => 'La quantité B10 doit être au moins 1.',
            'quantite_10.min' => 'La quantité 10 doit être au moins 1.',
            'societe.required' => 'Le nom de la société est obligatoire.',
            'societe.max' => 'Le nom de la société ne doit pas dépasser 255 caractères.',
            'email.required' => "L'adresse email est obligatoire.",
            'email.email' => "L'adresse email n'est pas valide.",
            'email.max' => "L'adresse email ne doit pas dépasser 255 caractères.",
            'telephone.required' => 'Le numéro de téléphone est obligatoire.',
            'telephone.max' => 'Le numéro de téléphone ne doit pas dépasser 20 caractères.',
        ]);

        $produit = Produit::create($validated);

        try {
            Mail::to($produit->email)->send(new CommandeProduitConfirmation($produit));
        } catch (Throwable $e) {
            Log::error("Échec de l'envoi de l'email de confirmation de commande", [
                'produit_id' => $produit->id,
                'email' => $produit->email,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Demande enregistrée avec succès.',
            'produit' => $produit,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Produit $produit): JsonResponse
    {
        return response()->json($produit);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Produit $produit): JsonResponse
    {
        $produit->delete();

        return response()->json(['message' => 'Demande supprimée avec succès.']);
    }
}
