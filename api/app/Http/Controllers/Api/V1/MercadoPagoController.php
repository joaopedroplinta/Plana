<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MercadoPagoStatusResource;
use App\Models\Tenant;
use App\Services\MercadoPagoOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Fase 1 do marketplace: conexão da conta MercadoPago do próprio salão via
 * OAuth. `connect`/`status`/`disconnect` vivem sob o prefixo de tenant
 * (auth + isolamento); `callback` é PÚBLICO (é o MercadoPago que chama).
 */
class MercadoPagoController extends Controller
{
    public function __construct(private readonly MercadoPagoOAuthService $oauth) {}

    /**
     * Inicia o fluxo OAuth: gera a URL de autorização (com state anti-CSRF)
     * para o frontend redirecionar o dono do salão ao MercadoPago.
     * Apenas o owner do tenant pode conectar a conta.
     */
    public function connect(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        if (! $request->user()->ownsTenant($tenant)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return response()->json([
            'data' => [
                'authorization_url' => $this->oauth->authorizationUrl($tenant),
            ],
        ]);
    }

    /**
     * Callback público do OAuth. O MercadoPago redireciona para cá com
     * `code` e `state`; validamos o state, trocamos o code por tokens e
     * devolvemos o usuário ao frontend (dashboard de settings do salão).
     */
    public function callback(Request $request): RedirectResponse
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');

        if ($code === '' || $state === '') {
            return redirect()->away("{$frontend}/dashboard/settings?mp=error");
        }

        $tenant = $this->oauth->handleCallback($code, $state);

        if (! $tenant) {
            return redirect()->away("{$frontend}/dashboard/settings?mp=error");
        }

        return redirect()->away("{$frontend}/{$tenant->slug}/dashboard/settings?mp=connected");
    }

    /**
     * Estado da conexão MercadoPago do salão. Owner e staff podem consultar.
     * Nunca retorna tokens (ver MercadoPagoStatusResource).
     */
    public function status(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        if (! $request->user()->isStaffOfTenant($tenant)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return (new MercadoPagoStatusResource($tenant))->response();
    }

    /**
     * Desconecta a conta MercadoPago do salão (limpa todas as colunas mp_*).
     * Apenas o owner pode desconectar.
     */
    public function disconnect(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        if (! $request->user()->ownsTenant($tenant)) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $this->oauth->disconnect($tenant);

        return response()->json(['data' => ['connected' => false]]);
    }
}
