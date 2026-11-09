<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class NoStore {
 public static function secure(Response $response):Response {
  $response->headers->set('Cache-Control','no-store, private');
  $response->headers->set('Pragma','no-cache');
  $response->headers->set('X-Content-Type-Options','nosniff');
  $response->headers->set('X-Frame-Options','DENY');
  $response->headers->set('Referrer-Policy','strict-origin-when-cross-origin');
  $response->headers->set('Permissions-Policy','camera=(), microphone=(), geolocation=()');
  $response->headers->set('Content-Security-Policy',"default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
  return $response;
 }
 public function handle(Request $request,Closure $next){return self::secure($next($request));}
}
