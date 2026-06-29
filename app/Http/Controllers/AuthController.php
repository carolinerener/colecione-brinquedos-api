<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        Log::info('Novo cadastro', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            Log::warning('Tentativa de login falhou', [
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        Log::info('Login realizado', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        Log::info('Logout realizado', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Exclusão de conta com anonimização (LGPD - Art. 18, VI).
     *
     * Conforme Art. 16, I da LGPD, dados podem ser mantidos quando há obrigação
     * legal — no caso, pedidos/notas fiscais por 5 anos. Por isso anonimizamos
     * o usuário em vez de apagar fisicamente: nome, email, senha e endereços
     * cadastrados são substituídos por valores genéricos, mas o ID do usuário
     * é preservado para manter integridade referencial com a tabela orders.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
            'confirmacao' => 'required|in:EXCLUIR MINHA CONTA',
        ], [
            'password.required' => 'Confirme sua senha para excluir a conta.',
            'confirmacao.required' => 'Digite a confirmação para prosseguir.',
            'confirmacao.in' => 'Texto de confirmação incorreto.',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            Log::warning('Tentativa de exclusão de conta com senha incorreta', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'password' => ['Senha incorreta.'],
            ]);
        }

        $userIdOriginal = $user->id;
        $emailOriginal = $user->email;

        DB::transaction(function () use ($user) {
            // Anonimiza endereços cadastrados (separados dos endereços de pedidos)
            $user->addresses()->update([
                'street' => 'Removido',
                'number' => '0',
                'complement' => null,
                'neighborhood' => 'Removido',
                'city' => 'Removido',
                'state' => 'XX',
                'zipcode' => '00000000',
            ]);

            // Anonimiza dados pessoais do usuário
            $sufixoUnico = $user->id . '_' . time();

            $user->update([
                'name' => 'Usuário Removido',
                'email' => "removido_{$sufixoUnico}@anonimo.local",
                'password' => Hash::make(Str::random(60)),
            ]);

            // Revoga todos os tokens (logout em todos os dispositivos)
            $user->tokens()->delete();

            // Soft delete (preenche deleted_at, mantém ID para integridade fiscal)
            $user->delete();
        });

        Log::info('Conta excluída pelo usuário (LGPD)', [
            'user_id_original' => $userIdOriginal,
            'email_original' => $emailOriginal,
            'ip' => $request->ip(),
            'data_exclusao' => now()->toIso8601String(),
        ]);

        return response()->json([
            'message' => 'Conta excluída com sucesso.',
        ]);
    }
}