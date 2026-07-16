<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fase 1 do marketplace MercadoPago: liga a conta MercadoPago do próprio
 * salão (tenant) à plataforma via OAuth (Authorization Code), para que os
 * pagamentos de agendamento/pacote caiam na conta do salão.
 *
 * Deliberadamente usa o Laravel HTTP client (`Http`) — e não o SDK — para as
 * chamadas ao `/oauth/token`, de modo que os testes possam mocká-las com
 * `Http::fake()`. Comissão da plataforma = 0 nesta fase (sem
 * marketplace_fee/application_fee).
 */
class MercadoPagoOAuthService
{
    private const STATE_CACHE_PREFIX = 'mercadopago_oauth_state:';

    private const STATE_TTL_SECONDS = 600; // 10 minutos

    private const AUTHORIZATION_ENDPOINT = 'https://auth.mercadopago.com/authorization';

    private const TOKEN_ENDPOINT = 'https://api.mercadopago.com/oauth/token';

    /**
     * Gera a URL de autorização OAuth e grava o `state` anti-CSRF no cache
     * apontando para o tenant que iniciou o fluxo.
     */
    public function authorizationUrl(Tenant $tenant): string
    {
        $state = Str::random(40);

        Cache::put(self::STATE_CACHE_PREFIX.$state, $tenant->id, self::STATE_TTL_SECONDS);

        $query = http_build_query([
            'client_id' => config('services.mercadopago.app_id'),
            'response_type' => 'code',
            'platform_id' => 'mp',
            'state' => $state,
            'redirect_uri' => config('services.mercadopago.redirect_uri'),
        ]);

        return self::AUTHORIZATION_ENDPOINT.'?'.$query;
    }

    /**
     * Resolve o tenant a partir do `state` (validando-o no cache) e troca o
     * `code` por tokens, persistindo-os criptografados no tenant. Devolve o
     * tenant conectado em caso de sucesso, ou `null` se o state for
     * inválido/expirado ou a troca de tokens falhar. O state é invalidado
     * após o uso (one-time).
     */
    public function handleCallback(string $code, string $state): ?Tenant
    {
        $cacheKey = self::STATE_CACHE_PREFIX.$state;
        $tenantId = Cache::get($cacheKey);

        if (! $tenantId) {
            return null;
        }

        Cache::forget($cacheKey);

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return null;
        }

        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'client_id' => config('services.mercadopago.app_id'),
            'client_secret' => config('services.mercadopago.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.mercadopago.redirect_uri'),
        ]);

        if ($response->failed()) {
            // Nunca logamos o corpo da resposta (pode conter tokens).
            Log::warning('MercadoPago OAuth: troca de code por token falhou.', [
                'tenant_id' => $tenant->id,
                'status' => $response->status(),
            ]);

            return null;
        }

        $this->storeTokens($tenant, $response->json());

        return $tenant;
    }

    /**
     * Retorna um access token válido para o tenant, renovando-o via
     * refresh_token se estiver expirado. Devolve `null` quando o tenant não
     * tem conta conectada (o chamador deve, nesse caso, cair no token
     * global da plataforma).
     */
    public function accessTokenFor(Tenant $tenant): ?string
    {
        if (! $tenant->hasMercadoPagoConnected()) {
            return null;
        }

        if ($tenant->mercadoPagoTokenExpired()) {
            $this->refreshToken($tenant);
        }

        return $tenant->mp_access_token;
    }

    /**
     * Renova o access token usando o refresh_token armazenado. Silencioso em
     * caso de falha (o token atual continua e o chamador pode cair no
     * fallback global).
     */
    public function refreshToken(Tenant $tenant): void
    {
        if (empty($tenant->mp_refresh_token)) {
            return;
        }

        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'client_id' => config('services.mercadopago.app_id'),
            'client_secret' => config('services.mercadopago.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $tenant->mp_refresh_token,
        ]);

        if ($response->failed()) {
            Log::warning('MercadoPago OAuth: refresh de token falhou.', [
                'tenant_id' => $tenant->id,
                'status' => $response->status(),
            ]);

            return;
        }

        $this->storeTokens($tenant, $response->json());
    }

    /**
     * Desconecta a conta MercadoPago do salão, limpando todas as colunas mp_*.
     */
    public function disconnect(Tenant $tenant): void
    {
        $tenant->forceFill([
            'mp_access_token' => null,
            'mp_refresh_token' => null,
            'mp_user_id' => null,
            'mp_public_key' => null,
            'mp_token_expires_at' => null,
            'mp_connected_at' => null,
        ])->save();
    }

    /**
     * Persiste (criptografado) o payload de tokens do MercadoPago no tenant.
     *
     * @param  array<string, mixed>  $payload
     */
    private function storeTokens(Tenant $tenant, array $payload): void
    {
        $attributes = [
            'mp_access_token' => $payload['access_token'] ?? $tenant->mp_access_token,
            'mp_refresh_token' => $payload['refresh_token'] ?? $tenant->mp_refresh_token,
            'mp_user_id' => isset($payload['user_id']) ? (string) $payload['user_id'] : $tenant->mp_user_id,
            'mp_public_key' => $payload['public_key'] ?? $tenant->mp_public_key,
            'mp_connected_at' => $tenant->mp_connected_at ?? now(),
        ];

        if (isset($payload['expires_in'])) {
            $attributes['mp_token_expires_at'] = now()->addSeconds((int) $payload['expires_in']);
        }

        $tenant->forceFill($attributes)->save();
    }
}
