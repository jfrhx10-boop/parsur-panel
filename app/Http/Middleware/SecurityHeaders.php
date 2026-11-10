<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad para todo el panel y fuera de buscadores.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        // Los documentos propios (p. ej. el PDF de un albarán subido) sí se pueden mostrar dentro de una pantalla del panel
        $frameSelf = $response->headers->has('X-Panel-Frame');
        $response->headers->remove('X-Panel-Frame');
        $response->headers->set('X-Frame-Options', $frameSelf ? 'SAMEORIGIN' : 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self' blob:; object-src 'self' blob:; img-src 'self' data: blob: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' 'wasm-unsafe-eval' blob:; worker-src 'self' blob:; frame-src 'self' blob:; frame-ancestors ".($frameSelf ? "'self'" : "'none'")."; base-uri 'self'; form-action 'self' mailto: https://wa.me"
        );
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        // Las pantallas con datos no se guardan en caché del navegador ni del servidor.
        $response->headers->set('Cache-Control', 'no-store, private');

        // Si queda trabajo para después de responder (guardar en FactuSOL), la respuesta se cierra ya:
        // longitud exacta y sin compresión, para que el navegador no espere a que termine.
        if (\App\Services\AfterResponse::pending() && ! ($response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) && ! ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse)) {
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }
            @ini_set('zlib.output_compression', '0');
            $response->headers->set('Content-Length', (string) strlen((string) $response->getContent()));
            $response->headers->set('Connection', 'close');
            $response->headers->set('Content-Encoding', 'identity');
            $response->headers->set('X-Accel-Buffering', 'no');
        }

        return $response;
    }
}
