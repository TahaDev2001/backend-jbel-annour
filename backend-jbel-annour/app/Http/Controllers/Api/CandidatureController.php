<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\CandidatureConfirmation;
use App\Mail\CandidatureNotificationAdmin;
use App\Models\Candidature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CandidatureController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        return response()->json(Candidature::latest()->paginate(15));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'cv' => 'required|file|mimes:pdf,doc,docx|max:5120',
        ], [
            'nom.required' => 'Le nom est obligatoire.',
            'nom.max' => 'Le nom ne doit pas dépasser 100 caractères.',
            'prenom.required' => 'Le prénom est obligatoire.',
            'prenom.max' => 'Le prénom ne doit pas dépasser 100 caractères.',
            'email.required' => "L'adresse email est obligatoire.",
            'email.email' => "L'adresse email n'est pas valide.",
            'email.max' => "L'adresse email ne doit pas dépasser 255 caractères.",
            'cv.required' => 'Le CV est obligatoire.',
            'cv.file' => 'Le CV doit être un fichier.',
            'cv.mimes' => 'Le CV doit être au format PDF, DOC ou DOCX.',
            'cv.max' => 'Le CV ne doit pas dépasser 5 Mo.',
        ]);

        $validated['cv'] = $request->file('cv')->store('cvs', 'public');

        $candidature = Candidature::create($validated);

        try {
            Mail::to($candidature->email)->send(new CandidatureConfirmation($candidature));
        } catch (Throwable $e) {
            Log::error("Échec de l'envoi de l'email de confirmation de candidature", [
                'candidature_id' => $candidature->id,
                'email' => $candidature->email,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            Mail::to(config('mail.admin_address'))->send(new CandidatureNotificationAdmin($candidature));
        } catch (Throwable $e) {
            Log::error("Échec de l'envoi de la notification admin de candidature", [
                'candidature_id' => $candidature->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Candidature enregistrée avec succès.',
            'candidature' => $candidature,
            'cv_url' => Storage::disk('public')->url($candidature->cv),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Candidature $candidature): JsonResponse
    {
        return response()->json([
            'candidature' => $candidature,
            'cv_url' => Storage::disk('public')->url($candidature->cv),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Candidature $candidature): JsonResponse
    {
        Storage::disk('public')->delete($candidature->cv);

        $candidature->delete();

        return response()->json(['message' => 'Candidature supprimée avec succès.']);
    }
}
