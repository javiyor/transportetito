<?php
namespace App\Http\Middleware;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
class TrustProxies extends Middleware
{
    /**
     * Trust all proxies (Caddy -> Nginx -> PHP-FPM in Docker).
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';
    /**
     * Use all forwarded headers (proto/host/port/prefix).
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_PREFIX |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
