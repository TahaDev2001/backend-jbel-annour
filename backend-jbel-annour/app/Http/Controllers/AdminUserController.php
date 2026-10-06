<?php

namespace App\Http\Controllers;

use App\Models\ApmNewUser;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    // GET /api/admin/users?status=pending|approved|rejected
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        return response()->json(
            ApmNewUser::where('status', $status)->orderByDesc('created_at')->get()
        );
    }

    public function approve(Request $request, int $id)
    {
        $user = ApmNewUser::findOrFail($id);
        $user->status = 'approved';
        $user->validated_at = now();
        $user->validated_by = $request->user()->id;
        $user->save();

        return response()->json(['message' => 'Utilisateur validé.', 'user' => $user]);
    }

    public function reject(Request $request, int $id)
    {
        $user = ApmNewUser::findOrFail($id);
        $user->status = 'rejected';
        $user->validated_at = now();
        $user->validated_by = $request->user()->id;
        $user->save();

        $user->tokens()->delete(); // coupe ses sessions s'il était déjà validé

        return response()->json(['message' => 'Utilisateur refusé.', 'user' => $user]);
    }
}
