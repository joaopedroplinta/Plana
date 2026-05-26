<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Handle an incoming request.
     *
     * Resolves the tenant from the {tenant:slug} route parameter.
     * Stores it in the IoC container (app('currentTenant')) and on the request.
     * Returns 404 if the tenant is not found or inactive.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant');

        // Route model binding may already have resolved this to a Tenant instance.
        if ($slug instanceof Tenant) {
            $tenant = $slug;
        } else {
            $tenant = Tenant::where('slug', $slug)->first();
        }

        if (! $tenant || ! $tenant->active) {
            abort(404, 'Salão não encontrado ou inativo.');
        }

        // Bind tenant globally and on the request object.
        app()->instance('currentTenant', $tenant);
        $request->tenant = $tenant;

        return $next($request);
    }
}
