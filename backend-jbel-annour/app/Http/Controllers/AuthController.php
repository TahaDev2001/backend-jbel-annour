<?php

namespace App\Http\Controllers;

use App\Models\ApmNewUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'nom_complet' => ['required', 'string', 'max:150'],
            'societe' => ['required', 'string', 'max:150'],
            'ice' => ['required', 'digits:15'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:facturation.apm_new_users,username'],
            'email' => ['required', 'email', 'max:150', 'unique:facturation.apm_new_users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        ApmNewUser::create($data); // password hashé par le cast, status = pending par défaut

        return response()->json([
            'message' => "Inscription enregistrée. Votre compte doit être validé par l'administrateur avant de pouvoir vous connecter.",
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = ApmNewUser::where('username', $data['username'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => "Nom d'utilisateur ou mot de passe incorrect."], 401);
        }

        if ($user->status === 'pending') {
            return response()->json(['message' => "Votre compte est en attente de validation par l'administrateur."], 403);
        }

        if ($user->status === 'rejected') {
            return response()->json(['message' => 'Votre compte a été refusé.'], 403);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }
}